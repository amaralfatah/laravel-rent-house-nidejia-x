<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\Store;
use App\Models\Listing;
use App\Models\Transaction;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    public function index(): JsonResponse
    {
        $transactions = Transaction::with('Listing')->whereUserId(Auth::id())->paginate();

        return response()->json([
            'success' => true,
            'message' => 'List of transactions',
            'data' => $transactions,
        ]);
    }

    /**
     * Check if the listing is fully booked for the given date range.
     *
     * @param Store $request
     * @return bool
     * @throws HttpResponseException
     */
    private function _fullyBookedChecker(Store $request)
    {
        // Find the listing by ID
        $listing = Listing::find($request->listing_id);

        // Count the number of running transactions that overlap with the requested date range
        $runningTransactionCount = Transaction::whereListingId($listing->id)
            ->whereNot('status', 'canceled')
            ->where(function ($query) use ($request) {
                $query->whereBetween('start_date', [
                    $request->start_date,
                    $request->end_date
                ])
                    ->orWhereBetween('end_date', [
                        $request->start_date,
                        $request->end_date
                    ])
                    ->orWhere(function ($subQuery) use ($request) {
                        $subQuery->where('start_date', '<', $request->start_date)
                            ->where('end_date', '>', $request->end_date);
                    });
            })
            ->count(); // Add count() to get the number of transactions

        // Check if the number of running transactions exceeds the maximum allowed
        if ($runningTransactionCount >= $listing->max_person) {
            // Throw an exception if the listing is fully booked
            throw new HttpResponseException(
                response()->json([
                    'success' => false,
                    'message' => 'Listing is fully booked',
                ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY)
            );
        }

        return true;
    }

    public function isAvailable(Store $request)
    {
        $this->_fullyBookedChecker($request);

        return response()->json([
            'success' => true,
            'message' => 'Listing is available',
        ]);
    }

    public function store(Store $request)
    {
        $this->_fullyBookedChecker($request);

        // Create a new transaction
        $transaction = Transaction::create([
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'listing_id' => $request->listing_id,
            'user_id' => Auth::id(),
        ]);

        $transaction->Listing;

        return response()->json([
            'success' => true,
            'message' => 'New Transaction created',
            'data' => $transaction,
        ]);
    }

    public function show(Transaction $transaction): JsonResponse
    {
        if ($transaction->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $transaction->Listing;

        return response()->json([
            'success' => true,
            'message' => 'Detail of transaction',
            'data' => $transaction,
        ]);
    }
}
