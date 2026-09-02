@extends('admin.layouts.layout')
@section('title', 'SimplePay beállítások')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="header-box my-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="mb-0">SimplePay beállítások</h1>
                        <p class="text-muted small mb-0">SimplePay v2 fizetőkapu konfigurálása</p>
                    </div>
                    <div>
                        <button type="button" id="simplepay-test-connection-btn" class="btn btn-warning font-weight-bold">
                            <i class="fa fa-plug mr-1"></i> Kapcsolat tesztelése
                        </button>
                        <div id="simplepay-test-connection-result" class="mt-2 mb-0 d-none"></div>
                    </div>
                </div>
            </div>

            @include('admin.webshop.partials.alerts')

            @php
                $spEnabled = filter_var($spSettings['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $spIsLive = ($spSettings['environment'] ?? 'sandbox') === 'live';
                $spHasKeys = !empty($spSettings['merchant']) && !empty($spSettings['secret_key']);

                // Az IPN URL-t a site domainre kell megadni a SimplePay vezérlőpultján;
                // a route() itt az admin domaint adná.
                $spScheme = request()->getScheme();
                $spHost = getSiteDomain();
                $spPort = request()->getPort();
                $spDefaultPort = $spScheme === 'https' ? 443 : 80;
                $spBase = $spScheme . '://' . $spHost
                    . (($spPort && (int) $spPort !== $spDefaultPort && strpos($spHost, ':') === false) ? ':' . $spPort : '');
            @endphp

            <form action="{{ route('admin.webshop.simplepay.settings.update') }}" method="POST">
                @csrf

                <div class="row">
                    <div class="col-lg-6">
                        <div class="header-box product-info mb-1">Modul állapota</div>
                        <div class="content-box bordered mb-3">
                            <div class="custom-control custom-switch mb-3">
                                <input type="checkbox" class="custom-control-input" id="enabled" name="enabled"
                                       @if($spEnabled) checked @endif>
                                <label class="custom-control-label fw-600" for="enabled">SimplePay fizetés engedélyezve</label>
                            </div>

                            <div class="form-group mb-2">
                                <label class="fw-600">Megnevezés a pénztárban</label>
                                <input type="text" name="payment_method_label" class="form-control"
                                       value="{{ $spSettings['payment_method_label'] ?? '' }}"
                                       placeholder="SimplePay bankkártyás fizetés">
                                <span class="text-muted fs-14">Ez a szöveg jelenik meg a vásárlónak fizetési módként.</span>
                            </div>

                            @if($spEnabled && !$spHasKeys)
                                <div class="alert alert-warning mb-0 py-2 px-3 small">
                                    <i class="fa fa-exclamation-triangle mr-1"></i>
                                    A modul be van kapcsolva, de hiányzik a kereskedői azonosító vagy a SECRET_KEY –
                                    a fizetés indítása hibára fut.
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="header-box product-info mb-1">Környezet</div>
                        <div class="content-box bordered mb-3">
                            <div class="form-group mb-3">
                                <label class="fw-600">API környezet</label>
                                <select name="environment" id="environment" class="form-control">
                                    <option value="sandbox" @if(!$spIsLive) selected @endif>Sandbox (teszt)</option>
                                    <option value="live" @if($spIsLive) selected @endif>Éles</option>
                                </select>
                                <span class="text-muted fs-14">
                                    A két rendszer teljesen elkülönül: külön kereskedői fiók és külön SECRET_KEY tartozik hozzájuk.
                                </span>
                            </div>

                            @if($spIsLive)
                                <div class="alert alert-danger mb-0 py-2 px-3 small">
                                    <i class="fa fa-exclamation-triangle mr-1"></i>
                                    <strong>Éles környezet.</strong> A vásárlók valódi pénzzel fizetnek.
                                </div>
                            @else
                                <div class="alert alert-secondary mb-0 py-2 px-3 small">
                                    <i class="fa fa-flask mr-1"></i>
                                    Sandbox: csak a fizetőoldalon található tesztkártyákkal lehet tranzakciót indítani.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="header-box product-info mb-1">Kereskedői fiók</div>
                <div class="content-box bordered mb-3">
                    <div class="row">
                        <div class="col-lg-4">
                            <div class="form-group mb-0">
                                <label class="fw-600">Kereskedő azonosító (MERCHANT)</label>
                                <input type="text" name="merchant" class="form-control"
                                       value="{{ $spSettings['merchant'] ?? '' }}" placeholder="pl. PUBLICTESTHUF">
                                <span class="text-muted fs-14">A kereskedői vezérlőpultról, a fiók technikai adatai közül.</span>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="form-group mb-0">
                                <label class="fw-600">SECRET_KEY</label>
                                <input type="password" name="secret_key" class="form-control"
                                       value="{{ $spSettings['secret_key'] ?? '' }}" autocomplete="new-password">
                                <span class="text-muted fs-14">Titkosítva tárolódik. Üresen hagyva a korábbi marad.</span>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="form-group mb-0">
                                <label class="fw-600">Pénznem</label>
                                <select name="currency" class="form-control">
                                    @foreach(['HUF' => 'HUF – forint', 'EUR' => 'EUR – euró', 'USD' => 'USD – dollár'] as $v => $l)
                                        <option value="{{ $v }}" @if(($spSettings['currency'] ?? 'HUF') === $v) selected @endif>{{ $l }}</option>
                                    @endforeach
                                </select>
                                <span class="text-muted fs-14">A SimplePay devizanemenként külön fiókot ad.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6">
                        <div class="header-box product-info mb-1">Fizetőoldal</div>
                        <div class="content-box bordered mb-3">
                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="fw-600">Fizetőoldal nyelve</label>
                                        <select name="language" class="form-control">
                                            @foreach(['HU' => 'Magyar', 'EN' => 'Angol', 'DE' => 'Német'] as $v => $l)
                                                <option value="{{ $v }}" @if(($spSettings['language'] ?? 'HU') === $v) selected @endif>{{ $l }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="fw-600">Fizetési ablak (perc)</label>
                                        <input type="text" name="timeout_minutes" class="form-control"
                                               value="{{ $spSettings['timeout_minutes'] ?? '30' }}" placeholder="30">
                                        <span class="text-muted fs-14">Eddig kezdheti el a vásárló a fizetést.</span>
                                    </div>
                                </div>
                            </div>

                            <div class="custom-control custom-checkbox mb-0">
                                <input type="checkbox" class="custom-control-input" id="log_payloads" name="log_payloads"
                                       @if(filter_var($spSettings['log_payloads'] ?? true, FILTER_VALIDATE_BOOLEAN)) checked @endif>
                                <label class="custom-control-label" for="log_payloads">API hívások naplózása</label>
                            </div>
                            <span class="text-muted fs-14">A SECRET_KEY soha nem kerül naplózásra.</span>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="header-box product-info mb-1">IPN (értesítés a fizetés eredményéről)</div>
                        <div class="content-box bordered mb-3">
                            <div class="form-group mb-2">
                                <label class="fw-600">IPN URL</label>
                                <input type="text" class="form-control" readonly onclick="this.select()"
                                       value="{{ $spBase }}/commerce/simplepay/ipn">
                                <span class="text-muted fs-14">
                                    Ezt a címet kell beállítani a SimplePay kereskedői vezérlőpultján
                                    (Technikai adatok), <strong>minden fiókon külön-külön</strong>.
                                </span>
                            </div>

                            <div class="alert alert-info py-2 px-3 small">
                                <i class="fa fa-info-circle mr-1"></i>
                                A rendelés véglegesítése az IPN alapján történik, nem a vásárló visszatérésekor –
                                így akkor is lezárul, ha a vásárló fizetés után bezárja a böngészőt.
                            </div>

                            <div class="custom-control custom-checkbox mb-0">
                                <input type="checkbox" class="custom-control-input" id="ipn_ip_check" name="ipn_ip_check"
                                       @if(filter_var($spSettings['ipn_ip_check'] ?? false, FILTER_VALIDATE_BOOLEAN)) checked @endif>
                                <label class="custom-control-label" for="ipn_ip_check">IPN forrás-IP ellenőrzése</label>
                            </div>
                            <span class="text-muted fs-14">
                                Az aláírás-ellenőrzés önmagában is elegendő védelem. Proxy vagy CDN mögött
                                ez a szűrés téves elutasításhoz vezethet, ezért alapból ki van kapcsolva.
                            </span>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-3 mb-5">
                    <button type="submit" class="btn btn-primary fs-18 font-weight-bold px-5">
                        <i class="fa fa-save mr-1"></i> Beállítások mentése
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('simplepay-test-connection-btn');
        var out = document.getElementById('simplepay-test-connection-result');

        // Beágyazott visszajelzés natív alert() helyett: az blokkolja a lapot.
        function show(isSuccess, message) {
            out.className = 'mt-2 mb-0 alert ' + (isSuccess ? 'alert-success' : 'alert-danger');
            out.textContent = message;
        }

        btn.addEventListener('click', function () {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Tesztelés...';
            out.className = 'mt-2 mb-0 d-none';
            out.textContent = '';

            fetch('{{ route("admin.webshop.simplepay.test-connection") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                show(!!data.success, data.message || '');
            })
            .catch(function () {
                show(false, 'Váratlan hiba történt a tesztelés során.');
            })
            .finally(function () {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-plug mr-1"></i> Kapcsolat tesztelése';
            });
        });

        // A mentetlen környezetváltás félreértést okozna: a teszt mindig a
        // mentett beállítással fut, ezért erre figyelmeztetünk.
        var envSelect = document.getElementById('environment');
        if (envSelect) {
            var savedEnv = envSelect.value;
            envSelect.addEventListener('change', function () {
                if (envSelect.value !== savedEnv) {
                    show(false, 'A környezetváltás csak mentés után lép érvénybe – a kapcsolat tesztelése addig a korábbi beállítással fut.');
                }
            });
        }
    });
</script>
@endsection
