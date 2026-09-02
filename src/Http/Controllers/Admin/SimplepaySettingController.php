<?php

namespace Weboldalnet\CommerceSimplepay\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Weboldalnet\CommerceSimplepay\Services\SimplepayClient;
use Weboldalnet\CommerceSimplepay\Services\SimplepaySettingsService;

class SimplepaySettingController extends Controller
{
    public function index()
    {
        // FIGYELEM: a változó neve nem lehet $settings – a platform admin layoutja
        // egy globálisan megosztott $settings modellt használ, azt felülírnánk.
        $spSettings = SimplepaySettingsService::all();

        // A DB-ben még nem szereplő mezőknél is a tényleges (config/.env) érték látszódjon
        foreach (SimplepaySettingsService::viewKeys() as $key) {
            if (!array_key_exists($key, $spSettings)) {
                $spSettings[$key] = SimplepaySettingsService::get($key);
            }
        }

        // Titkosított mezők maszkolása
        foreach (SimplepaySettingsService::encryptedKeys() as $key) {
            if (!empty($spSettings[$key])) {
                $spSettings[$key] = '********';
            }
        }

        return view('commerce-simplepay::admin.settings', compact('spSettings'));
    }

    public function update(Request $request)
    {
        $data = $request->all();
        $booleanKeys = SimplepaySettingsService::booleanKeys();
        $encryptedKeys = SimplepaySettingsService::encryptedKeys();

        foreach ($data as $key => $value) {
            if ($key === '_token') {
                continue;
            }

            $type = 'string';

            if (in_array($key, $booleanKeys, true)) {
                $type = 'boolean';
                $value = ($value === 'on' || $value === '1' || $value === true);
            } elseif (in_array($key, $encryptedKeys, true)) {
                $type = 'encrypted';
                // A maszkolt értéket nem mentjük vissza
                if ($value === '********') {
                    continue;
                }
            }

            SimplepaySettingsService::save($key, $value, $type);
        }

        // A be nem küldött checkboxok kikapcsoltnak számítanak
        foreach ($booleanKeys as $key) {
            if (!isset($data[$key])) {
                SimplepaySettingsService::save($key, false, 'boolean');
            }
        }

        return redirect()->back()->with('success', 'SimplePay beállítások sikeresen mentve.');
    }

    /**
     * Kapcsolat és kereskedői adatok ellenőrzése.
     *
     * A SimplePay-nek nincs külön "ping" végpontja, ezért egy nem létező
     * tranzakciót kérdezünk le. Ha a kereskedői azonosító vagy a SECRET_KEY hibás,
     * a SimplePay hibakóddal válaszol, illetve a válasz aláírása nem fog egyezni –
     * így a teszt valódi ellenőrzés, nem csak elérhetőség-vizsgálat.
     */
    public function testConnection(SimplepayClient $client)
    {
        if (!SimplepaySettingsService::hasCredentials()) {
            return response()->json([
                'success' => false,
                'message' => 'Hiányzó kereskedői azonosító vagy SECRET_KEY.',
            ]);
        }

        $environmentLabel = SimplepaySettingsService::isLive() ? 'éles' : 'sandbox';

        try {
            $result = $client->query(['WN-KAPCSOLAT-TESZT-' . date('YmdHis')]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Hiba a kapcsolódáskor: ' . $e->getMessage(),
            ]);
        }

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'A kereskedői adatok érvényesek, a SimplePay elérhető (' . $environmentLabel . ' környezet).',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Sikertelen (' . $environmentLabel . ' környezet): ' . $result['message'],
        ]);
    }
}
