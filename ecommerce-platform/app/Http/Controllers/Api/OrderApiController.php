<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\CartException;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * OrderApiController
 *
 * All endpoints require authentication (auth:sanctum).
 * Business logic is entirely delegated to OrderService.
 *
 * Customer Endpoints:
 *   GET    /api/v1/orders                   → index   (my orders)
 *   POST   /api/v1/orders                   → store   (checkout)
 *   GET    /api/v1/orders/{orderNumber}      → show    (order detail)
 *   POST   /api/v1/orders/{orderNumber}/cancel → cancel
 *
 * Admin / Vendor Endpoints:
 *   GET    /api/v1/admin/orders             → adminIndex (all orders)
 *   PUT    /api/v1/admin/orders/{id}/status → updateStatus
 *   PUT    /api/v1/admin/orders/{id}/ship   → markShipped
 */
class OrderApiController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    // ── Customer: My Orders ──────────────────────────────────────────────

    /**
     * GET /api/v1/orders
     *
     * Returns the authenticated customer's orders, newest first.
     * Supports filtering by status.
     *
     * Query Parameters:
     *   status   string   pending|paid|processing|shipped|delivered|cancelled|refunded
     *   per_page int      default 15
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status'   => ['nullable', Rule::in([
                              'pending','paid','processing','shipped',
                              'delivered','cancelled','refunded',
                           ])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $perPage = (int) $request->get('per_page', 15);

        $orders = Order::with('items')
            ->forUser($request->user()->id)
            ->when($request->status, fn ($q) => $q->byStatus($request->status))
            ->recent()
            ->paginate($perPage);

        return OrderResource::collection($orders);
    }

    // ── Customer: Place Order ────────────────────────────────────────────

    /**
     * POST /api/v1/orders
     *
     * Convert the authenticated user's cart into a confirmed order.
     *
     * Body:
     *   shipping_address_id  int     required — FK to user's addresses
     *   payment_method       string  required — stripe|iyzipay|cod|bank_transfer
     *   payment_reference    string  optional — gateway charge/token ID
     *   coupon_code          string  optional
     *   notes                string  optional — delivery instructions
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shipping_address_id' => ['required', 'integer', 'exists:addresses,id'],
            'payment_method'      => ['required', 'string', Rule::in([
                                         'stripe', 'iyzipay', 'cod', 'bank_transfer',
                                     ])],
            'payment_reference'   => ['nullable', 'string', 'max:255'],
            'coupon_code'         => ['nullable', 'string', 'max:60'],
            'notes'               => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $order = $this->orderService->createOrderFromCart(
                userId:            $request->user()->id,
                shippingAddressId: $validated['shipping_address_id'],
                paymentMethod:     $validated['payment_method'],
                paymentReference:  $validated['payment_reference'] ?? '',
                couponCode:        $validated['coupon_code']        ?? null,
                notes:             $validated['notes']              ?? null,
            );

            return response()->json([
                'message' => 'Order placed successfully.',
                'data'    => new OrderResource($order),
            ], 201);

        } catch (CartException $e) {
            return response()->json($e->toArray(), 422);
        } catch (InsufficientStockException $e) {
            return response()->json($e->toArray(), 422);
        } catch (\Throwable $e) {
            // Log the full exception for debugging but return a clean message
            \Illuminate\Support\Facades\Log::error('Order creation failed', [
                'user_id' => $request->user()->id,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Order could not be placed. Please try again.',
                'error'   => 'server_error',
            ], 500);
        }
    }

    // ── Customer: Order Detail ───────────────────────────────────────────

    /**
     * GET /api/v1/orders/{orderNumber}
     *
     * Returns the full detail of a single order.
     * Customers can only access their own orders.
     */
    public function show(Request $request, string $orderNumber): JsonResponse
    {
        $order = Order::with(['items.review'])
            ->where('order_number', $orderNumber)
            ->forUser($request->user()->id)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        return response()->json(['data' => new OrderResource($order)]);
    }

    // ── Customer: Cancel Order ───────────────────────────────────────────

    /**
     * POST /api/v1/orders/{orderNumber}/cancel
     *
     * Cancel a pending or paid order and restore stock.
     */
    public function cancel(Request $request, string $orderNumber): JsonResponse
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $order = Order::where('order_number', $orderNumber)
            ->forUser($request->user()->id)
            ->firstOrFail();

        try {
            $cancelled = $this->orderService->cancelOrder($order, $request->reason);

            return response()->json([
                'message' => 'Order cancelled successfully.',
                'data'    => new OrderResource($cancelled),
            ]);

        } catch (CartException $e) {
            return response()->json($e->toArray(), 422);
        }
    }

    // ── Admin: All Orders ────────────────────────────────────────────────

    /**
     * GET /api/v1/admin/orders
     *
     * Full order listing for admins with filtering by status, date range,
     * customer, and vendor. Offset-paginated (for admin tables, not infinite scroll).
     *
     * Query Parameters:
     *   status       string   filter by order status
     *   user_id      int      filter by customer
     *   vendor_id    int      filter by vendor (via order items)
     *   date_from    date     Y-m-d
     *   date_to      date     Y-m-d
     *   search       string   order number or customer name
     *   per_page     int      default 25
     */
    public function adminIndex(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status'    => ['nullable', Rule::in([
                               'pending','paid','processing','shipped',
                               'delivered','cancelled','refunded',
                            ])],
            'user_id'   => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date', 'after_or_equal:date_from'],
            'search'    => ['nullable', 'string', 'max:80'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) $request->get('per_page', 25);

        $orders = Order::with('items', 'user:id,name,email')
            ->when($request->status,    fn ($q) => $q->byStatus($request->status))
            ->when($request->user_id,   fn ($q) => $q->where('user_id', $request->user_id))
            ->when($request->date_from, fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to,   fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->when($request->search,    fn ($q) => $q->where(fn ($inner) =>
                $inner->where('order_number', 'like', '%' . $request->search . '%')
                      ->orWhereHas('user', fn ($u) =>
                          $u->where('name', 'like', '%' . $request->search . '%')
                            ->orWhere('email', 'like', '%' . $request->search . '%')
                      )
            ))
            ->recent()
            ->paginate($perPage);

        return OrderResource::collection($orders);
    }

    // ── Admin: Update Order Status ────────────────────────────────────────

    /**
     * PUT /api/v1/admin/orders/{id}/status
     *
     * Generic status transition (e.g. paid → processing).
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                             'paid', 'processing', 'shipped', 'delivered',
                             'cancelled', 'refunded',
                         ])],
        ]);

        $order = Order::with('items')->findOrFail($id);

        $order->transitionTo($validated['status']);

        return response()->json([
            'message' => "Order status updated to \"{$validated['status']}\".",
            'data'    => new OrderResource($order->refresh()),
        ]);
    }

    // ── Admin / Vendor: Mark Shipped ──────────────────────────────────────

    /**
     * PUT /api/v1/admin/orders/{id}/ship
     *
     * Record dispatch and set tracking information.
     */
    public function markShipped(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'carrier'       => ['required', 'string', 'max:80'],
            'tracking_code' => ['required', 'string', 'max:120'],
        ]);

        $order = Order::with('items')->findOrFail($id);

        $shipped = $this->orderService->markAsShipped(
            $order,
            $validated['carrier'],
            $validated['tracking_code'],
        );

        return response()->json([
            'message' => 'Order marked as shipped.',
            'data'    => new OrderResource($shipped),
        ]);
    }
}
