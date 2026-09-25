@props(['login' => false])

@php
    use App\Support\BotCheck;
    use App\Support\SecuritySettings;

    $provider = BotCheck::captchaFor((bool) $login);
@endphp

@if (SecuritySettings::get('honeypot'))
    <div aria-hidden="true" style="position: absolute; left: -10000px; top: auto; width: 1px; height: 1px; overflow: hidden">
        <label for="{{ BotCheck::HONEYPOT }}-field">Leave this empty</label>
        <input type="text" id="{{ BotCheck::HONEYPOT }}-field" name="{{ BotCheck::HONEYPOT }}" value="" tabindex="-1" autocomplete="off" />
    </div>
    <input type="hidden" name="{{ BotCheck::STARTED }}" value="{{ BotCheck::startedToken() }}" />
@endif

@if ($provider)
    <div
        class="{{ $provider === 'turnstile' ? 'cf-turnstile' : 'h-captcha' }} flex justify-center"
        data-sitekey="{{ SecuritySettings::get('captcha_site_key') }}"
        data-theme="auto"
    ></div>
    @once
        <script src="{{ BotCheck::SCRIPTS[$provider] }}" async defer></script>
    @endonce
@endif
