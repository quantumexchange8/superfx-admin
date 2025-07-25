<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\PammController;
use App\Http\Controllers\ForumController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\RebateController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\GeneralController;
use App\Http\Controllers\NetworkController;
use App\Http\Controllers\PendingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\BillboardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AccountTypeController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\MarkupProfileController;
use App\Http\Controllers\TradePositionController;
use App\Http\Controllers\TradingAccountController;

Route::get('locale/{locale}', function ($locale) {
    App::setLocale($locale);
    Session::put("locale", $locale);

    return redirect()->back();
});

Route::get('/', function () {
    return redirect(route('login'));
});

Route::post('transaction_callback', [PendingController::class, 'transactionCallback'])->name('transactionCallback');

Route::middleware(['auth', 'role:super-admin|admin'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/getPendingCounts', [DashboardController::class, 'getPendingCounts'])->name('dashboard.getPendingCounts');
    Route::get('/getAccountData', [DashboardController::class, 'getAccountData'])->name('dashboard.getAccountData');
    Route::get('/getPendingData', [DashboardController::class, 'getPendingData'])->name('dashboard.getPendingData');
    Route::get('/getAssetData', [DashboardController::class, 'getAssetData'])->name('dashboard.getAssetData');
    Route::get('/getAccountTypes', [GeneralController::class, 'getAccountTypes'])->name('getAccountTypes');
    Route::get('/getTransactionMonths', [GeneralController::class, 'getTransactionMonths'])->name('getTransactionMonths');
    Route::get('/getLeverages', [GeneralController::class, 'getLeverages'])->name('getLeverages');
    Route::get('/getAccountGroups', [GeneralController::class, 'getAccountGroups'])->name('getAccountGroups');

    /**
     * ==============================
     *           Pending
     * ==============================
     */
    Route::prefix('pending')->group(function () {
        Route::get('/', [PendingController::class, 'index'])->name('pending');
        Route::get('/getPendingWithdrawalData', [PendingController::class, 'getPendingWithdrawalData'])->name('pending.getPendingWithdrawalData');
        Route::get('/getPendingRevokeData', [PendingController::class, 'getPendingRevokeData'])->name('pending.getPendingRevokeData');

        Route::post('withdrawalApproval', [PendingController::class, 'withdrawalApproval'])->name('pending.withdrawalApproval');
        Route::post('revokeApproval', [PendingController::class, 'revokeApproval'])->name('pending.revokeApproval');
    });

    /**
     * ==============================
     *           Member
     * ==============================
     */
    Route::prefix('member')->group(function () {
        // listing
        Route::get('/listing', [MemberController::class, 'listing'])->name('member.listing');
        Route::get('/getMemberListingData', [MemberController::class, 'getMemberListingData'])->name('member.getMemberListingData');
        Route::get('/getMemberListingPaginate', [MemberController::class, 'getMemberListingPaginate'])->name('member.getMemberListingPaginate');
        Route::get('/getFilterData', [MemberController::class, 'getFilterData'])->name('member.getFilterData');
        Route::get('/getAvailableUplines', [MemberController::class, 'getAvailableUplines'])->name('member.getAvailableUplines');
        Route::get('/getUplineData', [MemberController::class, 'getUplineData'])->name('member.getUplineData');
        Route::get('/getAvailableUplineData', [MemberController::class, 'getAvailableUplineData'])->name('member.getAvailableUplineData');
        Route::get('/access_portal/{user}', [MemberController::class, 'access_portal'])->name('member.access_portal');

        Route::post('/addNewMember', [MemberController::class, 'addNewMember'])->name('member.addNewMember');
        Route::post('/updateMemberStatus', [MemberController::class, 'updateMemberStatus'])->name('member.updateMemberStatus');
        Route::post('/transferUpline', [MemberController::class, 'transferUpline'])->name('member.transferUpline');
        Route::post('/upgradeIB', [MemberController::class, 'upgradeIB'])->name('member.upgradeIB');
        Route::post('/resetPassword', [MemberController::class, 'resetPassword'])->name('member.resetPassword');
        Route::post('/verifyEmail', [MemberController::class, 'verifyEmail'])->name('member.verifyEmail');
        Route::post('/uploadKyc', [MemberController::class, 'uploadKyc'])->name('member.uploadKyc');

        Route::delete('/deleteMember', [MemberController::class, 'deleteMember'])->name('member.deleteMember');

        // details
        Route::get('/detail/{id_number}', [MemberController::class, 'detail'])->name('member.detail');
        Route::get('/getUserData', [MemberController::class, 'getUserData'])->name('member.getUserData');
        Route::get('/getFinancialInfoData', [MemberController::class, 'getFinancialInfoData'])->name('member.getFinancialInfoData');
        Route::get('/getTradingAccounts', [MemberController::class, 'getTradingAccounts'])->name('member.getTradingAccounts');
        Route::get('/getAdjustmentHistoryData', [MemberController::class, 'getAdjustmentHistoryData'])->name('member.getAdjustmentHistoryData');

        Route::post('/updateContactInfo', [MemberController::class, 'updateContactInfo'])->name('member.updateContactInfo');
        Route::post('/updateCryptoWalletInfo', [MemberController::class, 'updateCryptoWalletInfo'])->name('member.updateCryptoWalletInfo');
        Route::post('/updatePaymentAccount', [MemberController::class, 'updatePaymentAccount'])->name('member.updatePaymentAccount');
        Route::post('/updateKYCStatus', [MemberController::class, 'updateKYCStatus'])->name('member.updateKYCStatus');
        Route::post('/walletAdjustment', [MemberController::class, 'walletAdjustment'])->name('member.walletAdjustment');

        // network
        Route::get('/network', [NetworkController::class, 'network'])->name('member.network');
        Route::get('/getDownlineData', [NetworkController::class, 'getDownlineData'])->name('member.getDownlineData');

        // forum
        Route::get('/forum', [ForumController::class, 'index'])->name('member.forum');
        Route::get('/getPosts', [ForumController::class, 'getPosts'])->name('member.getPosts');
        Route::get('/getIBs', [ForumController::class, 'getIBs'])->name('member.getIBs');

        Route::post('/createPost', [ForumController::class, 'createPost'])->name('member.createPost');
        Route::post('/updatePostPermission', [ForumController::class, 'updatePostPermission'])->name('member.updatePostPermission');
        Route::delete('/deletePost', [ForumController::class, 'deletePost'])->name('member.deletePost');

        // account listing
        Route::get('/account_listing', [TradingAccountController::class, 'index'])->name('member.account_listing');
        Route::get('/getAccountListingData', [TradingAccountController::class, 'getAccountListingData'])->name('member.getAccountListingData');
        Route::get('/getAccountListingPaginate', [TradingAccountController::class, 'getAccountListingPaginate'])->name('member.getAccountListingPaginate');
        Route::get('/getTradingAccountData', [TradingAccountController::class, 'getTradingAccountData'])->name('member.getTradingAccountData');

        Route::post('/accountAdjustment', [TradingAccountController::class, 'accountAdjustment'])->name('member.accountAdjustment');
        Route::post('/updateLeverage', [TradingAccountController::class, 'updateLeverage'])->name('member.updateLeverage');
        Route::post('/updateAccountGroup', [TradingAccountController::class, 'updateAccountGroup'])->name('member.updateAccountGroup');
        Route::post('/change_password', [TradingAccountController::class, 'change_password'])->name('member.change_password');
        Route::post('/refreshAllAccount', [TradingAccountController::class, 'refreshAllAccount'])->name('member.refreshAllAccount');
        Route::delete('/accountDelete', [TradingAccountController::class, 'accountDelete'])->name('member.accountDelete');
    });

    /**
     * ==============================
     *            Group
     * ==============================
     */
    Route::prefix('group')->group(function () {
        Route::get('/', [GroupController::class, 'show'])->name('group');
        Route::get('/getGroups', [GroupController::class, 'getGroups'])->name('group.getGroups');
        Route::get('/getIBs', [GroupController::class, 'getIBs'])->name('group.getIBs');
        Route::get('/getGroupTransaction', [GroupController::class, 'getGroupTransaction'])->name('group.getGroupTransaction');
        Route::get('/refreshGroup', [GroupController::class, 'refreshGroup'])->name('group.refreshGroup');
        Route::get('/getSettlementReport', [GroupController::class, 'getSettlementReport'])->name('group.getSettlementReport');

        Route::post('/create_group', [GroupController::class, 'createGroup'])->name('group.create');
        Route::post('/markSettlementReport/{id}', [GroupController::class, 'markSettlementReport'])->name('group.markSettlementReport');

        Route::patch('/edit_group/{id}', [GroupController::class, 'editGroup'])->name('group.edit');

        Route::delete('/delete_group/{id}', [GroupController::class, 'deleteGroup'])->name('group.delete');
    });

    /**
     * ==============================
     *        Pamm Allocate
     * ==============================
     */
    Route::prefix('pamm_allocate')->group(function () {
        Route::get('/', [PammController::class, 'pamm_allocate'])->name('pamm_allocate');
        Route::get('/getMasters', [PammController::class, 'getMasters'])->name('pamm_allocate.getMasters');
        Route::get('/getMetrics', [PammController::class, 'getMetrics'])->name('pamm_allocate.getMetrics');
        Route::get('/getOptions', [PammController::class, 'getOptions'])->name('pamm_allocate.getOptions');
        Route::get('/getProfitLoss', [PammController::class, 'getProfitLoss'])->name('pamm_allocate.getProfitLoss');
        Route::get('/getJoiningPammAccountsData', [PammController::class, 'getJoiningPammAccountsData'])->name('pamm_allocate.getJoiningPammAccountsData');
        Route::get('/getRevokePammAccountsData', [PammController::class, 'getRevokePammAccountsData'])->name('pamm_allocate.getRevokePammAccountsData');
        Route::get('/getPammAccountsDataCount', [PammController::class, 'getPammAccountsDataCount'])->name('pamm_allocate.getPammAccountsDataCount');
        Route::get('/getMasterMonthlyProfit', [PammController::class, 'getMasterMonthlyProfit'])->name('pamm_allocate.getMasterMonthlyProfit');

        Route::post('/validateStep', [PammController::class, 'validateStep'])->name('pamm_allocate.validateStep');
        Route::post('/create_asset_master', [PammController::class, 'create_asset_master'])->name('pamm_allocate.create_asset_master');
        Route::post('/edit_asset_master', [PammController::class, 'edit_asset_master'])->name('pamm_allocate.edit_asset_master');
        Route::post('/update_asset_master_status', [PammController::class, 'update_asset_master_status'])->name('pamm_allocate.update_asset_master_status');
        Route::post('/disband', [PammController::class, 'disband'])->name('pamm_allocate.disband');
        Route::post('/updateLikeCounts', [PammController::class, 'updateLikeCounts'])->name('pamm_allocate.updateLikeCounts');
        Route::post('/addProfitDistribution', [PammController::class, 'addProfitDistribution'])->name('pamm_allocate.addProfitDistribution');
    });

    /**
     * ==============================
     *        Rebate Allocate
     * ==============================
     */
    Route::prefix('rebate_allocate')->group(function () {
        Route::get('/', [RebateController::class, 'rebate_allocate'])->name('rebate_allocate');
        Route::get('/getCompanyProfileData', [RebateController::class, 'getCompanyProfileData'])->name('rebate_allocate.getCompanyProfileData');
        Route::get('/getRebateStructureData', [RebateController::class, 'getRebateStructureData'])->name('rebate_allocate.getRebateStructureData');
        Route::get('/getIBs', [RebateController::class, 'getIBs'])->name('rebate_allocate.getIBs');
        Route::get('/changeIBs', [RebateController::class, 'changeIBs'])->name('rebate_allocate.changeIBs');

        Route::post('/updateRebateAllocation', [RebateController::class, 'updateRebateAllocation'])->name('rebate_allocate.updateRebateAllocation');
        Route::post('/updateRebateAmount', [RebateController::class, 'updateRebateAmount'])->name('rebate_allocate.updateRebateAmount');
    });

    /**
     * ==============================
     *          Billboard
     * ==============================
     */
    Route::prefix('billboard')->group(function () {
        Route::get('/', [BillboardController::class, 'index'])->name('billboard');
        Route::get('/getBonusProfiles', [BillboardController::class, 'getBonusProfiles'])->name('billboard.getBonusProfiles');
        Route::get('/getIBs', [BillboardController::class, 'getIBs'])->name('billboard.getIBs');
        Route::get('/getBonusWithdrawalData', [BillboardController::class, 'getBonusWithdrawalData'])->name('billboard.getBonusWithdrawalData');
        Route::get('/getStatementData', [BillboardController::class, 'getStatementData'])->name('billboard.getStatementData');
        Route::get('/getBonusWithdrawalHistories', [BillboardController::class, 'getBonusWithdrawalHistories'])->name('billboard.getBonusWithdrawalHistories');

        Route::post('/createBonusProfile', [BillboardController::class, 'createBonusProfile'])->name('billboard.createBonusProfile');
        Route::post('/editBonusProfile', [BillboardController::class, 'editBonusProfile'])->name('billboard.editBonusProfile');

    });

    /**
     * ==============================
     *          Transaction
     * ==============================
     */
    Route::prefix('transaction')->group(function () {
        Route::get('/', [TransactionController::class, 'listing'])->name('transaction');
        Route::get('/getTransactionListingData', [TransactionController::class, 'getTransactionListingData'])->name('transaction.getTransactionListingData');
        Route::get('/getTransactionMonths', [TransactionController::class, 'getTransactionMonths'])->name('transaction.getTransactionMonths');

    });

    /**
     * ==============================
     *         Trade Position
     * ==============================
     */
    Route::prefix('trade_positions')->group(function () {
        Route::get('/open_positions', [TradePositionController::class, 'open_positions'])->name('trade_positions.open_positions');
        Route::get('/open_trade', [TradePositionController::class, 'open_trade'])->name('trade_positions.open_trade');
        Route::get('/closed_positions', [TradePositionController::class, 'closed_positions'])->name('trade_positions.closed_positions');
        Route::get('/closed_trade', [TradePositionController::class, 'closed_trade'])->name('trade_positions.closed_trade');

        // Additional routes can be added as necessary
        // Route::get('/details/{id}', [TradePositionController::class, 'details'])->name('trade_positions.details');
    });
    
    /**
     * ==============================
     *          Report
     * ==============================
     */
    Route::prefix('report')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('report');
        Route::get('/getRebateSummary', [ReportController::class, 'getRebateSummary'])->name('report.getRebateSummary');
        Route::get('/getRebateListing', [ReportController::class, 'getRebateListing'])->name('report.getRebateListing');
        // Route::get('/getGroupTransaction', [ReportController::class, 'getGroupTransaction'])->name('report.getGroupTransaction');
        Route::get('/getRebateHistory', [ReportController::class, 'getRebateHistory'])->name('report.getRebateHistory');
    });

    /**
     * ==============================
     *         Markup Profile
     * ==============================
     */
    Route::prefix('markup_profile')->group(function () {
        Route::get('/', [MarkupProfileController::class, 'index'])->name('markup_profile');
        Route::get('/getMarkupProfiles', [MarkupProfileController::class, 'getMarkupProfiles'])->name('markup_profile.getMarkupProfiles');
        Route::get('/getMarkupProfileData', [MarkupProfileController::class, 'getMarkupProfileData'])->name('markup_profile.getMarkupProfileData');

        Route::post('/addMarkupProfile', [MarkupProfileController::class, 'addMarkupProfile'])->name('markup_profile.addMarkupProfile');

        Route::post('/updateMarkupProfile', [MarkupProfileController::class, 'updateMarkupProfile'])->name('markup_profile.updateMarkupProfile');

        Route::patch('/updateStatus', [MarkupProfileController::class, 'updateStatus'])->name('markup_profile.updateStatus');
    });

    /**
     * ==============================
     *         Account Type
     * ==============================
     */
    Route::prefix('account_type')->group(function () {
        Route::get('/', [AccountTypeController::class, 'show'])->name('accountType');
        Route::get('/getAccountTypes', [AccountTypeController::class, 'getAccountTypes'])->name('accountType.getAccountTypes');
        Route::get('/syncAccountTypes', [AccountTypeController::class, 'syncAccountTypes'])->name('accountType.syncAccountTypes');
        Route::get('/getAccountTypeUsers', [AccountTypeController::class, 'getAccountTypeUsers'])->name('accountType.getAccountTypeUsers');

        Route::post('/update/{id}', [AccountTypeController::class, 'updateAccountType'])->name('accountType.update');

        Route::patch('/updateStatus/{id}', [AccountTypeController::class, 'updateStatus'])->name('accountType.updateStatus');
    });

    /**
     * ==============================
     *           Profile
     * ==============================
     */
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'index'])->name('profile');

        Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::post('/updateProfilePhoto', [ProfileController::class, 'updateProfilePhoto'])->name('profile.updateProfilePhoto');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });
});

Route::get('/components/buttons', function () {
    return Inertia::render('Components/Buttons');
})->name('components.buttons');

Route::get('/test/component', function () {
    return Inertia::render('Welcome');
})->name('test.component');

require __DIR__.'/auth.php';
