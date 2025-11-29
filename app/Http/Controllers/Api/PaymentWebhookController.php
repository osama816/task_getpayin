<?php

namespace App\Http\Controllers;

use App\Models\PaymentWebhook;
use App\Http\Requests\StorePaymentWebhookRequest;
use App\Http\Requests\UpdatePaymentWebhookRequest;

class PaymentWebhookController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePaymentWebhookRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(PaymentWebhook $paymentWebhook)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PaymentWebhook $paymentWebhook)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePaymentWebhookRequest $request, PaymentWebhook $paymentWebhook)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PaymentWebhook $paymentWebhook)
    {
        //
    }
}
