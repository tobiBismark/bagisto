<?php

namespace Webkul\Admin\Http\Controllers\Settings;

use Illuminate\Support\Facades\Event;
use Illuminate\Http\Resources\Json\JsonResource;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Core\Repositories\ExchangeRateRepository;
use Webkul\Core\Repositories\CurrencyRepository;
use Webkul\Admin\DataGrids\ExchangeRatesDataGrid;

class ExchangeRateController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected ExchangeRateRepository $exchangeRateRepository,
        protected CurrencyRepository $currencyRepository
    ) {
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        if (request()->ajax()) {
            return app(ExchangeRatesDataGrid::class)->toJson();
        }

        $currencies = $this->currencyRepository->with('exchange_rate')->all();

        return view('admin::settings.exchange_rates.index', compact('currencies'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return JsonResource
     */
    public function store(): JsonResource
    {
        $this->validate(request(), [
            'target_currency' => ['required', 'unique:currency_exchange_rates,target_currency'],
            'rate'            => 'required|numeric',
        ]);

        Event::dispatch('core.exchange_rate.create.before');

        $exchangeRate = $this->exchangeRateRepository->create(request()->only([
            'target_currency',
            'rate'
        ]));

        Event::dispatch('core.exchange_rate.create.after', $exchangeRate);

        return new JsonResource([
            'message' => trans('admin::app.settings.exchange-rates.index.create.success'),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return JsonResource
     */
    public function edit($id): JsonResource
    {
        $currencies = $this->currencyRepository->all();

        $exchangeRate = $this->exchangeRateRepository->findOrFail($id);

        return new JsonResource([
            'currencies' => $currencies,
            'exchangeRate' => $exchangeRate,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return JsonResource
     */
    public function update(): JsonResource
    {
        $this->validate(request(), [
            'target_currency' => ['required', 'unique:currency_exchange_rates,target_currency,' . request()->id],
            'rate'            => 'required|numeric',
        ]);

        Event::dispatch('core.exchange_rate.update.before', request()->id);

        $exchangeRate = $this->exchangeRateRepository->update(request()->only([
            'target_currency',
            'rate'
        ]), request()->id);

        Event::dispatch('core.exchange_rate.update.after', $exchangeRate);

        return new JsonResource([
            'message' => trans('admin::app.settings.exchange-rates.index.edit.success'),
        ]);
    }

    /**
     * Update Rates Using Exchange Rates API
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateRates()
    {
        try {
            app(config('services.exchange_api.' . config('services.exchange_api.default') . '.class'))->updateRates();

            session()->flash('success', trans('admin::app.settings.exchange-rates.edit.update-success'));
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }

        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return JsonResource
     */
    public function destroy($id): JsonResource
    {
        $this->exchangeRateRepository->findOrFail($id);

        try {
            Event::dispatch('core.exchange_rate.delete.before', $id);

            $this->exchangeRateRepository->delete($id);

            Event::dispatch('core.exchange_rate.delete.after', $id);

            return new JsonResource([
                'message' => trans('admin::app.settings.exchange-rates.index.edit.delete'),
            ]);
        } catch (\Exception $e) {
            report($e);
        }

        return new JsonResource([
            'message' => trans('admin::app.response.delete-error', ['name' => trans('admin::app.settings.exchange-rates.index.exchange-rate')], 500),
        ]);
    }
}