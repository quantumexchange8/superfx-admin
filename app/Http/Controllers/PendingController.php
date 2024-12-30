<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Wallet;
use App\Models\AssetRevoke;
use App\Models\TradingUser;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\TradingAccount;
// use App\Services\CTraderService;
use App\Models\AssetSubscription;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\RunningNumberService;
use App\Services\ChangeTraderBalanceType;
use Illuminate\Validation\ValidationException;

class PendingController extends Controller
{
    public function index()
    {
        return Inertia::render('Pending/Pending');
    }

    public function getPendingWithdrawalData(Request $request)
    {
        $pendingWithdrawals = Transaction::with([
            'user:id,email,name',
            'payment_account:id,payment_account_name,account_no',
        ])
            ->where('transaction_type', 'withdrawal')
            ->where('status', 'processing')
            ->whereNot('category', 'bonus_wallet')
            ->latest()
            ->get()
            ->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'created_at' => $transaction->created_at,
                    'user_name' => $transaction->user->name,
                    'user_email' => $transaction->user->email,
                    'user_profile_photo' => $transaction->user->getFirstMediaUrl('profile_photo'),
                    'from' => $transaction->category == 'trading_account' ? $transaction->from_meta_login : 'rebate_wallet',
                    'balance' => $transaction->category == 'trading_account' ? $transaction->from_meta_login->balance : $transaction->from_wallet->balance,
                    'amount' => $transaction->amount,
                    'transaction_charges' => $transaction->transaction_charges,
                    'transaction_amount' => $transaction->transaction_amount,
                    'wallet_name' => $transaction->payment_account->payment_account_name,
                    'wallet_address' => $transaction->payment_account->account_no,
                ];
            });

        $totalAmount = $pendingWithdrawals->sum('amount');

        return response()->json([
            'pendingWithdrawals' => $pendingWithdrawals,
            'totalAmount' => $totalAmount,
        ]);
    }

    public function withdrawalApproval(Request $request)
    {
        $action = $request->action;

        $status = $action == 'approve' ? 'successful' : 'rejected';

        $transaction = Transaction::find($request->id);

        if ($transaction->status != 'processing') {
            return redirect()->back()->with('toast', [
                'title' => 'Invalid action. Please try again.',
                'type' => 'warning'
            ]);
        }

        $transaction->update([
            'remarks' => $request->remarks,
            'status' => $status,
            'approved_at' => now(),
            'handle_by' => Auth::id()
        ]);

        if ($transaction->status == 'rejected') {

            if ($transaction->category == 'rebate_wallet') {
                $rebate_wallet = Wallet::where('user_id', $transaction->user_id)
                    ->where('type', 'rebate_wallet')
                    ->first();

                $transaction->update([
                    'old_wallet_amount' => $rebate_wallet->balance,
                    'new_wallet_amount' => $rebate_wallet->balance += $transaction->amount,
                ]);

                $rebate_wallet->save();
            }

            if ($transaction->category == 'bonus_wallet') {
                $bonus_wallet = Wallet::where('user_id', $transaction->user_id)
                    ->where('type', 'bonus_wallet')
                    ->first();

                $transaction->update([
                    'old_wallet_amount' => $bonus_wallet->balance,
                    'new_wallet_amount' => $bonus_wallet->balance += $transaction->amount,
                ]);

                $bonus_wallet->save();
            }

            if ($transaction->category == 'trading_account') {

                // try {
                //     $trade = (new CTraderService)->createTrade($transaction->from_meta_login, $transaction->amount, $transaction->remarks, ChangeTraderBalanceType::DEPOSIT);

                //     $transaction->update([
                //         'ticket' => $trade->getTicket(),
                //     ]);
                // } catch (\Throwable $e) {
                //     if ($e->getMessage() == "Not found") {
                //         TradingUser::firstWhere('meta_login', $transaction->from_meta_login)->update(['acc_status' => 'Inactive']);
                //     } else {
                //         Log::error($e->getMessage());
                //     }
                //     return back()
                //         ->with('toast', [
                //             'title' => 'Trading account error',
                //             'type' => 'error'
                //         ]);
                // }
            }

            return redirect()->back()->with('toast', [
                'title' => trans('public.toast_reject_withdrawal_request'),
                'type' => 'success'
            ]);
        } else {
            return redirect()->back()->with('toast', [
                'title' => trans('public.toast_approve_withdrawal_request'),
                'type' => 'success'
            ]);
        }
    }

    public function getPendingRevokeData(Request $request)
    {
        $pendingRevokes = AssetRevoke::with([
                'user:id,email,name',
                'asset_master:id,asset_name',
            ])
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->map(function ($revoke) {
                return [
                    'id' => $revoke->id,
                    'created_at' => $revoke->created_at,
                    'user_name' => $revoke->user->name,
                    'user_email' => $revoke->user->email,
                    'user_profile_photo' => $revoke->user->getFirstMediaUrl('profile_photo'),
                    'meta_login' => $revoke->meta_login,
                    'asset_master_name' => $revoke->asset_master->asset_name,
                    'amount' => $revoke->penalty_fee,
                ];
            });

        $totalAmount = $pendingRevokes->sum('amount');

        return response()->json([
            'pendingRevokes' => $pendingRevokes,
            'totalAmount' => $totalAmount,
        ]);
    }

    public function revokeApproval(Request $request)
    {
        // Validate request data
        $request->validate([
            'id' => 'required|integer|exists:asset_revokes,id',
            'remarks' => 'required|string|max:255',
        ]);

        // // Check connection status
        // $conn = (new CTraderService)->connectionStatus();
        // if ($conn['code'] != 0) {
        //     return back()->with('toast', [
        //         'title' => 'Connection Error',
        //         'type' => 'error'
        //     ]);
        // }

        // Find the AssetRevoke record or fail
        $assetRevoke = AssetRevoke::findOrFail($request->id);

        // Update the AssetRevoke record
        $assetRevoke->update([
            'status' => 'revoked',
            'remarks' => $request->remarks,
            'approval_at' => now(),
            'handle_by' => Auth::id(),
        ]);

        // Update the related AssetSubscription record using the relationship
        $assetRevoke->asset_subscription()->update([
            'status' => 'revoked',
            'remarks' => $request->remarks,
            'revoked_at' => now(),
        ]);

        // // Create a trade using CTraderService
        // try {
        //     $trade = (new CTraderService)->createTrade($assetRevoke->meta_login,$assetRevoke->penalty_fee,$request->remarks,ChangeTraderBalanceType::WITHDRAW);

        //     // Create a new Transaction record
        //     Transaction::create([
        //         'user_id' => $assetRevoke->user_id,
        //         'category' => 'trading_account',
        //         'transaction_type' => 'penalty_fee',
        //         'from_meta_login' => $assetRevoke->meta_login,
        //         'ticket' => $trade->getTicket(),
        //         'transaction_number' => RunningNumberService::getID('transaction'),
        //         'amount' => $assetRevoke->penalty_fee,
        //         'transaction_amount' => $assetRevoke->penalty_fee,
        //         'status' => 'successful',
        //         'remarks' => 'System Approval',
        //         'approved_at' => now(),
        //         'handle_by' => Auth::id(),
        //     ]);

        // } catch (\Throwable $e) {
        //     if ($e->getMessage() == "Not found") {
        //         TradingUser::firstWhere('meta_login', $assetRevoke->meta_login)->update(['acc_status' => 'Inactive']);
        //     } else {
        //         Log::error('Error creating trade or transaction: ' . $e->getMessage());
        //     }

        //     return back()->with('toast', [
        //         'title' => 'Trading account error',
        //         'type' => 'error'
        //     ]);
        // }

        // Return a success response
        return redirect()->back()->with('toast', [
            'title' => trans('public.toast_approve_revoke_request'),
            'type' => 'success'
        ]);
    }
}
