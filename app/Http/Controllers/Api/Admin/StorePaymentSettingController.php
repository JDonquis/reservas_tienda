<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\AuthorizesStoreAccess;
use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;

class StorePaymentSettingController extends Controller
{
    use AuthorizesStoreAccess;

    protected array $providers = ['paypal', 'mercadopago', 'stripe'];

    public function index(Request $request, Store $store)
    {
        $this->authorizeStore($request->user(), $store);

        $settings = $store->paymentSettings()->get()->keyBy('provider');

        $providers = [];
        foreach ($this->providers as $provider) {
            $setting = $settings->get($provider);

            $providers[$provider] = [
                'enabled' => (bool) ($setting->enabled ?? false),
                'mode' => $setting->mode ?? 'sandbox',
                'public_key' => $this->mask($setting->public_key ?? null),
                'secret_key' => $this->mask($setting->secret_key ?? null),
                'configured' => (bool) ($setting->secret_key ?? false),
            ];
        }

        return response()->json([
            'currency' => $store->currency,
            'providers' => $providers,
        ]);
    }

    public function update(Request $request, Store $store)
    {
        $this->authorizeStore($request->user(), $store);

        $data = $request->validate([
            'currency' => 'required|string|size:3',
            'providers' => 'required|array',
            'providers.*.enabled' => 'required|boolean',
            'providers.*.mode' => 'required|in:sandbox,live',
            'providers.*.public_key' => 'nullable|string',
            'providers.*.secret_key' => 'nullable|string',
        ]);

        $store->update(['currency' => strtoupper($data['currency'])]);

        foreach ($data['providers'] as $provider => $config) {
            if (! in_array($provider, $this->providers, true)) {
                continue;
            }

            $setting = $store->paymentSettings()->firstOrNew(['provider' => $provider]);
            $setting->enabled = $config['enabled'];
            $setting->mode = $config['mode'];

            if (! empty($config['public_key'])) {
                $setting->public_key = $config['public_key'];
            }

            if (! empty($config['secret_key'])) {
                $setting->secret_key = $config['secret_key'];
            }

            $setting->save();
        }

        return $this->index($request, $store);
    }

    protected function mask(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $len = strlen($value);

        if ($len <= 8) {
            return str_repeat('*', $len);
        }

        return substr($value, 0, 4).str_repeat('*', $len - 8).substr($value, -4);
    }
}
