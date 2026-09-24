@php
    $canUpdate = auth()
        ->user()
        ->can("admin.settings.basic-details.update");
@endphp

<form
    @if ($canUpdate)
        action="{{ route("admin.settings.basic.update") }}"
        method="POST"
        enctype="multipart/form-data"
    @endif
    x-data="{
        submitting: false,
        canUpdate: {{ $canUpdate ? "true" : "false" }},
        phones: {{ json_encode(old("phones", $settings["phones"] ?? [""])) }},
        emails: {{ json_encode(old("emails", $settings["emails"] ?? [""])) }},
        social_links:
            {{ json_encode(old("social_links", $settings["social_links"] ?? [])) }},
        addresses:
            {{ json_encode(old("addresses", $settings["addresses"] ?? [])) }},
        addPhone() {
            if (this.canUpdate) this.phones.push('')
        },
        removePhone(i) {
            if (this.canUpdate) this.phones.splice(i, 1)
        },

        addEmail() {
            if (this.canUpdate) this.emails.push('')
        },
        removeEmail(i) {
            if (this.canUpdate) this.emails.splice(i, 1)
        },

        addSocial() {
            if (this.canUpdate) this.social_links.push({ platform: '', url: '' })
        },
        removeSocial(i) {
            if (this.canUpdate) this.social_links.splice(i, 1)
        },

        addAddress() {
            if (this.canUpdate)
                this.addresses.push({ label: '', text: '', map_iframe: '' })
        },
        removeAddress(i) {
            if (this.canUpdate) this.addresses.splice(i, 1)
        },
    }"
    x-on:submit="submitting = true"
    class="space-y-6"
>
    @csrf
    @method("POST")

    @unless ($canUpdate)
        @include("admin.settings.partials.read-only-notice")
    @endunless

    @include("admin.settings.partials.basic-info.general")

    @include("admin.settings.partials.basic-info.branding")

    @include("admin.settings.partials.basic-info.addresses")

    @include("admin.settings.partials.basic-info.contact")

    @include("admin.settings.partials.basic-info.social")

    @if ($canUpdate)
        @include("admin.settings.partials.save-bar", ["label" => "Save settings", "note" => "Saves the whole business profile: general, branding, addresses, contact and social."])
    @endif
</form>
