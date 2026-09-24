@php
    use App\Services\Theme\Theme;

    $example = <<<'HTML'
    <header class="bg-site-header text-site-header-text border-site-border border-b">
      <a class="text-site-header-active">Home</a>
    </header>

    <h1 class="font-heading text-site-heading">Welcome</h1>
    <p class="text-site-muted">Muted text</p>

    <a class="rounded-site bg-site-button text-site-button-text hover:bg-site-button-hover px-5 py-2.5">
      Get started
    </a>

    <div class="rounded-site-card bg-site-surface border-site-border border p-6">Card</div>
    HTML;
@endphp

<div class="space-y-5">
    <x-admin.card title="Build pages that follow these settings" icon="code">
        <div class="space-y-3 text-sm text-slate-600 dark:text-slate-300">
            <p>
                The website's
                <code class="rounded bg-slate-100 px-1 py-0.5 font-mono text-xs dark:bg-slate-800">&lt;body&gt;</code>
                already uses the page background, body text colour and fonts, and headings use the heading font and colour. For everything else use
                these Tailwind classes; when an admin changes a colour or font, every page follows without rebuilding.
            </p>
            <pre
                class="overflow-x-auto rounded-lg bg-slate-900 p-4 font-mono text-xs leading-relaxed text-slate-100"
            ><code>{{ $example }}</code></pre>
        </div>
    </x-admin.card>

    <x-admin.card
        title="Places on the site (slots)"
        text="Colour · background · border classes for each part. Opacity works too: bg-site-button/10."
        icon="layers"
        :padded="false"
    >
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-100 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-2.5 font-medium">Part</th>
                        <th class="px-4 py-2.5 font-medium">Classes</th>
                        <th class="px-4 py-2.5 font-medium">CSS variable</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach (Theme::SLOTS as $key => $slot)
                        <tr>
                            <td class="px-4 py-2 whitespace-nowrap text-slate-800 dark:text-slate-100">{{ $slot[1] }}</td>
                            <td class="px-4 py-2">
                                <span class="flex flex-wrap gap-1">
                                    @foreach (["bg-site-$key", "text-site-$key", "border-site-$key"] as $class)
                                        <button
                                            type="button"
                                            x-on:click="copy('{{ $class }}')"
                                            class="cursor-pointer rounded border border-slate-200 px-1.5 py-0.5 font-mono text-[11px] text-slate-600 hover:border-blue-300 hover:text-blue-700 dark:border-slate-700 dark:text-slate-300"
                                        >
                                            {{ $class }}
                                        </button>
                                    @endforeach
                                </span>
                            </td>
                            <td class="px-4 py-2 font-mono text-[11px] whitespace-nowrap text-slate-500 dark:text-slate-400">
                                var(--slot-{{ $key }})
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-admin.card>

    <x-admin.card title="Fonts, corners and colour variables" icon="file-text">
        <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-[max-content_1fr]">
            @foreach ([
                    'font-heading' => 'Heading font (headings use it already)',
                    'font-body' => 'Body font',
                    'rounded-site' => 'Corner style for buttons and fields',
                    'rounded-site-card' => 'Corner style for cards (never becomes a pill)',
                    'bg-theme-{name}' => 'Any colour variable by name, e.g. bg-theme-footer-bg. Also text-, border- and hover:bg-',
                    'var(--theme-{name})' => 'The same colour in CSS, e.g. in Settings → Scripts custom CSS',
                    'dark:' => 'Tailwind dark: classes on the website switch on only when Style → Dark mode follows the visitor\'s device',
                    'resources/css/website.css' => 'The website\'s own stylesheet. The admin panel uses resources/css/admin.css and never loads it'
                ]
                as $class => $text)
                <dt>
                    <button
                        type="button"
                        x-on:click="copy('{{ $class }}')"
                        class="cursor-pointer rounded border border-slate-200 px-1.5 py-0.5 font-mono text-xs text-slate-700 hover:border-blue-300 hover:text-blue-700 dark:border-slate-700 dark:text-slate-200"
                    >
                        {{ $class }}
                    </button>
                </dt>
                <dd class="text-slate-600 dark:text-slate-300">{{ $text }}</dd>
            @endforeach
        </dl>
        <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">
            The bg-theme-* classes are made from the saved colours, so they also work in HTML written in page and blog content, with no rebuild.
        </p>
    </x-admin.card>
</div>
