<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    class="scroll-smooth"
>
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="description"
        content="Smart Inventory Management System for products, purchases, sales, suppliers, customers and reports."
    >

    <title>
        Smart Inventory Management System
    </title>

    <link
        rel="preconnect"
        href="https://fonts.bunny.net"
    >

    <link
        href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800"
        rel="stylesheet"
    >

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])
</head>

<body
    class="min-h-screen overflow-x-hidden bg-slate-950 font-sans text-white"
>
    @php
        $slides = [
            [
                'image' => asset(
                    'images/landing/warehouse.png'
                ),

                'eyebrow' => 'SPECIAL LAUNCH OFFER',

                'title' => 'Manage Your Entire Inventory From One Place',

                'description' =>
                    'Track products, stock levels, purchases and warehouse movements with a modern inventory management experience.',

                'offer' =>
                    'Free setup for your first 100 products',

                'button' => 'Start Managing Inventory',
            ],

            [
                'image' => asset(
                    'images/landing/sales-pos.png'
                ),

                'eyebrow' => 'SMART SALES MANAGEMENT',

                'title' => 'Create Sales Faster and Keep Stock Accurate',

                'description' =>
                    'Complete customer sales, monitor due amounts and automatically update product stock after every transaction.',

                'offer' =>
                    'Fast billing with real-time stock updates',

                'button' => 'Explore Sales Features',
            ],

            [
                'image' => asset(
                    'images/landing/supplier.png'
                ),

                'eyebrow' => 'PURCHASE & SUPPLIER CONTROL',

                'title' => 'Stay Connected With Every Supplier',

                'description' =>
                    'Manage suppliers, purchase approvals, received quantities, purchase returns and outstanding payments.',

                'offer' =>
                    'Complete supplier history in one dashboard',

                'button' => 'Manage Purchases',
            ],

            [
                'image' => asset(
                    'images/landing/analytics.png'
                ),

                'eyebrow' => 'BUSINESS INSIGHTS',

                'title' => 'Turn Inventory Data Into Better Decisions',

                'description' =>
                    'View sales, purchases, low-stock products, inventory value, due amounts and performance reports.',

                'offer' =>
                    'Live reports for smarter business planning',

                'button' => 'View Smart Reports',
            ],
        ];
    @endphp

    {{-- Background decoration --}}
    <div
        class="pointer-events-none fixed inset-0 overflow-hidden"
        aria-hidden="true"
    >
        <div
            class="absolute -left-32 top-24 h-96 w-96 rounded-full bg-blue-600/10 blur-3xl"
        ></div>

        <div
            class="absolute -right-32 top-1/3 h-[30rem] w-[30rem] rounded-full bg-cyan-500/10 blur-3xl"
        ></div>

        <div
            class="absolute bottom-0 left-1/3 h-80 w-80 rounded-full bg-indigo-600/10 blur-3xl"
        ></div>
    </div>

    {{-- Header --}}
    <header
        class="relative z-40 border-b border-white/10 bg-slate-950/80 backdrop-blur-xl"
    >
        <div
            class="mx-auto flex min-h-20 w-full max-w-[1500px] items-center justify-between gap-4 px-5 sm:px-8 lg:px-12"
        >
            {{-- Brand --}}
            <a
                href="{{ url('/') }}"
                class="group flex items-center gap-3"
            >
                <div
                    class="flex size-11 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-blue-700 text-sm font-extrabold text-white shadow-lg shadow-blue-600/30 transition group-hover:-translate-y-0.5 group-hover:shadow-blue-500/40"
                >
                    SI
                </div>

                <div>
                    <p
                        class="text-base font-extrabold leading-tight text-white"
                    >
                        Smart Inventory
                    </p>

                    <p
                        class="mt-0.5 text-xs text-slate-400"
                    >
                        Management System
                    </p>
                </div>
            </a>

            {{-- Desktop Navigation --}}
            <nav
                class="hidden items-center gap-8 lg:flex"
                aria-label="Main navigation"
            >
                <a
                    href="#features"
                    class="text-sm font-semibold text-slate-300 transition hover:text-white"
                >
                    Features
                </a>

                <a
                    href="#solutions"
                    class="text-sm font-semibold text-slate-300 transition hover:text-white"
                >
                    Solutions
                </a>

                <a
                    href="#benefits"
                    class="text-sm font-semibold text-slate-300 transition hover:text-white"
                >
                    Benefits
                </a>
            </nav>

            {{-- Authentication --}}
            <div class="flex items-center gap-2 sm:gap-3">
                @auth
                    <a
                        href="{{ route('dashboard') }}"
                        class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-600 px-4 text-sm font-bold text-white shadow-lg shadow-blue-600/20 transition hover:-translate-y-0.5 hover:bg-blue-500"
                    >
                        Go to Dashboard
                    </a>
                @else
                    @if (Route::has('login'))
                        <a
                            href="{{ route('login') }}"
                            class="inline-flex min-h-11 items-center justify-center rounded-xl px-4 text-sm font-bold text-slate-200 transition hover:bg-white/10 hover:text-white"
                        >
                            Log in
                        </a>
                    @endif

                    @if (Route::has('register'))
                        <a
                            href="{{ route('register') }}"
                            class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-600 px-4 text-sm font-bold text-white shadow-lg shadow-blue-600/20 transition hover:-translate-y-0.5 hover:bg-blue-500 sm:px-5"
                        >
                            Register
                        </a>
                    @endif
                @endauth
            </div>
        </div>
    </header>

    <main class="relative z-10">
        {{-- Hero Section --}}
        <section
            class="mx-auto grid min-h-[calc(100vh-5rem)] w-full max-w-[1500px] items-center gap-10 px-5 py-12 sm:px-8 lg:grid-cols-[0.8fr_1.2fr] lg:px-12 lg:py-16"
        >
            {{-- Hero Content --}}
            <div class="max-w-2xl">
                <div
                    class="inline-flex items-center gap-2 rounded-full border border-blue-400/20 bg-blue-500/10 px-4 py-2 text-xs font-bold uppercase tracking-[0.16em] text-blue-300"
                >
                    <span
                        class="size-2 animate-pulse rounded-full bg-blue-400"
                    ></span>

                    Modern Business Management
                </div>

                <h1
                    class="mt-6 text-4xl font-extrabold leading-[1.08] tracking-tight text-white sm:text-5xl lg:text-6xl"
                >
                    Smarter Stock.

                    <span
                        class="bg-gradient-to-r from-blue-400 via-cyan-300 to-blue-500 bg-clip-text text-transparent"
                    >
                        Stronger Business.
                    </span>
                </h1>

                <p
                    class="mt-6 max-w-xl text-base leading-8 text-slate-300 sm:text-lg"
                >
                    Control products, purchases, sales, suppliers,
                    customers, payments and reports from one secure
                    inventory management platform.
                </p>

                <div
                    class="mt-8 flex flex-col gap-3 sm:flex-row"
                >
                    @auth
                        <a
                            href="{{ route('dashboard') }}"
                            class="inline-flex min-h-13 items-center justify-center gap-2 rounded-xl bg-blue-600 px-6 text-sm font-bold text-white shadow-xl shadow-blue-600/20 transition hover:-translate-y-0.5 hover:bg-blue-500"
                        >
                            Open Dashboard

                            <svg
                                class="size-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"
                                />
                            </svg>
                        </a>
                    @else
                        @if (Route::has('register'))
                            <a
                                href="{{ route('register') }}"
                                class="inline-flex min-h-13 items-center justify-center gap-2 rounded-xl bg-blue-600 px-6 text-sm font-bold text-white shadow-xl shadow-blue-600/20 transition hover:-translate-y-0.5 hover:bg-blue-500"
                            >
                                Get Started

                                <svg
                                    class="size-5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"
                                    />
                                </svg>
                            </a>
                        @endif
                    @endauth

                    <a
                        href="#features"
                        class="inline-flex min-h-13 items-center justify-center rounded-xl border border-white/15 bg-white/5 px-6 text-sm font-bold text-white transition hover:border-white/25 hover:bg-white/10"
                    >
                        Explore Features
                    </a>
                </div>

                {{-- Small benefits --}}
                <div
                    class="mt-10 grid max-w-xl grid-cols-2 gap-4 sm:grid-cols-3"
                >
                    <div>
                        <p class="text-2xl font-extrabold text-white">
                            Real-time
                        </p>

                        <p class="mt-1 text-xs text-slate-400">
                            Stock monitoring
                        </p>
                    </div>

                    <div>
                        <p class="text-2xl font-extrabold text-white">
                            Secure
                        </p>

                        <p class="mt-1 text-xs text-slate-400">
                            Roles and permissions
                        </p>
                    </div>

                    <div class="col-span-2 sm:col-span-1">
                        <p class="text-2xl font-extrabold text-white">
                            Complete
                        </p>

                        <p class="mt-1 text-xs text-slate-400">
                            Business reports
                        </p>
                    </div>
                </div>
            </div>

            {{-- Slider --}}
            <div
                id="inventory-slider"
                class="group relative min-h-[430px] overflow-hidden rounded-[2rem] border border-white/15 bg-slate-900 shadow-2xl shadow-black/40 sm:min-h-[530px]"
            >
                @foreach ($slides as $index => $slide)
                    <article
                        data-slide
                        class="absolute inset-0 transition-all duration-700 ease-in-out {{ $index === 0
                            ? 'visible translate-x-0 opacity-100'
                            : 'invisible translate-x-8 opacity-0' }}"
                        aria-hidden="{{ $index === 0 ? 'false' : 'true' }}"
                    >
                        <img
                            src="{{ $slide['image'] }}"
                            alt="{{ $slide['title'] }}"
                            class="absolute inset-0 h-full w-full object-cover"
                        >

                        {{-- Overlay --}}
                        <div
                            class="absolute inset-0 bg-gradient-to-r from-slate-950 via-slate-950/80 to-slate-950/20"
                        ></div>

                        <div
                            class="absolute inset-0 bg-gradient-to-t from-slate-950 via-transparent to-transparent"
                        ></div>

                        {{-- Slide Text --}}
                        <div
                            class="relative z-10 flex h-full max-w-xl flex-col justify-end p-7 sm:p-10 lg:p-12"
                        >
                            <p
                                class="text-xs font-extrabold uppercase tracking-[0.2em] text-blue-300"
                            >
                                {{ $slide['eyebrow'] }}
                            </p>

                            <h2
                                class="mt-4 text-3xl font-extrabold leading-tight text-white sm:text-4xl"
                            >
                                {{ $slide['title'] }}
                            </h2>

                            <p
                                class="mt-4 max-w-lg text-sm leading-7 text-slate-200 sm:text-base"
                            >
                                {{ $slide['description'] }}
                            </p>

                            {{-- Offer --}}
                            <div
                                class="mt-6 inline-flex w-fit items-center gap-3 rounded-xl border border-emerald-400/25 bg-emerald-400/10 px-4 py-3 backdrop-blur-md"
                            >
                                <div
                                    class="flex size-9 items-center justify-center rounded-lg bg-emerald-400/15 text-emerald-300"
                                >
                                    <svg
                                        class="size-5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
                                        />
                                    </svg>
                                </div>

                                <div>
                                    <p
                                        class="text-[10px] font-bold uppercase tracking-wider text-emerald-300"
                                    >
                                        Offer
                                    </p>

                                    <p
                                        class="text-sm font-bold text-white"
                                    >
                                        {{ $slide['offer'] }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach

                {{-- Navigation arrows --}}
                <button
                    id="previous-slide"
                    type="button"
                    aria-label="Previous slide"
                    class="absolute left-4 top-1/2 z-20 flex size-11 -translate-y-1/2 items-center justify-center rounded-full border border-white/15 bg-black/30 text-white opacity-0 backdrop-blur-md transition hover:bg-black/50 group-hover:opacity-100"
                >
                    <svg
                        class="size-5"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="m15.75 19.5-7.5-7.5 7.5-7.5"
                        />
                    </svg>
                </button>

                <button
                    id="next-slide"
                    type="button"
                    aria-label="Next slide"
                    class="absolute right-4 top-1/2 z-20 flex size-11 -translate-y-1/2 items-center justify-center rounded-full border border-white/15 bg-black/30 text-white opacity-0 backdrop-blur-md transition hover:bg-black/50 group-hover:opacity-100"
                >
                    <svg
                        class="size-5"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="m8.25 4.5 7.5 7.5-7.5 7.5"
                        />
                    </svg>
                </button>

                {{-- Dots --}}
                <div
                    class="absolute bottom-5 right-6 z-20 flex items-center gap-2"
                >
                    @foreach ($slides as $index => $slide)
                        <button
                            type="button"
                            data-slide-dot="{{ $index }}"
                            aria-label="Go to slide {{ $index + 1 }}"
                            class="h-2.5 rounded-full transition-all duration-300 {{ $index === 0
                                ? 'w-8 bg-blue-400'
                                : 'w-2.5 bg-white/40 hover:bg-white/70' }}"
                        ></button>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Feature Section --}}
        <section
            id="features"
            class="border-y border-white/10 bg-white/[0.025] py-20"
        >
            <div
                class="mx-auto w-full max-w-[1500px] px-5 sm:px-8 lg:px-12"
            >
                <div class="mx-auto max-w-3xl text-center">
                    <p
                        class="text-xs font-extrabold uppercase tracking-[0.2em] text-blue-400"
                    >
                        Complete Inventory Solution
                    </p>

                    <h2
                        class="mt-4 text-3xl font-extrabold text-white sm:text-4xl"
                    >
                        Everything Your Business Needs
                    </h2>

                    <p class="mt-4 text-base leading-7 text-slate-400">
                        A complete set of tools for managing inventory,
                        transactions, contacts and business reports.
                    </p>
                </div>

                <div
                    class="mt-12 grid gap-5 md:grid-cols-2 xl:grid-cols-4"
                >
                    {{-- Feature 1 --}}
                    <div
                        class="group rounded-2xl border border-white/10 bg-slate-900/60 p-6 transition hover:-translate-y-1 hover:border-blue-400/30 hover:bg-slate-900"
                    >
                        <div
                            class="flex size-12 items-center justify-center rounded-xl bg-blue-500/10 text-blue-400 transition group-hover:bg-blue-500 group-hover:text-white"
                        >
                            <svg
                                class="size-6"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25M21 7.5v9L12 21.75m0-9L3 7.5m9 5.25v9M3 7.5v9l9 5.25"
                                />
                            </svg>
                        </div>

                        <h3 class="mt-5 text-lg font-bold text-white">
                            Product & Stock
                        </h3>

                        <p class="mt-3 text-sm leading-6 text-slate-400">
                            Manage products, categories, brands, units,
                            stock levels and low-stock alerts.
                        </p>
                    </div>

                    {{-- Feature 2 --}}
                    <div
                        class="group rounded-2xl border border-white/10 bg-slate-900/60 p-6 transition hover:-translate-y-1 hover:border-cyan-400/30 hover:bg-slate-900"
                    >
                        <div
                            class="flex size-12 items-center justify-center rounded-xl bg-cyan-500/10 text-cyan-400 transition group-hover:bg-cyan-500 group-hover:text-white"
                        >
                            <svg
                                class="size-6"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M2.25 3h1.386a1.5 1.5 0 0 1 1.455 1.136L5.47 5.65m0 0h14.28l-1.5 6.75H7.125M5.47 5.65l1.655 6.75m0 0-.49 2.205A1.5 1.5 0 0 0 8.1 16.43h9.15m-8.625 3.32h.008v.008h-.008v-.008Zm7.5 0h.008v.008h-.008v-.008Z"
                                />
                            </svg>
                        </div>

                        <h3 class="mt-5 text-lg font-bold text-white">
                            Purchases & Sales
                        </h3>

                        <p class="mt-3 text-sm leading-6 text-slate-400">
                            Control purchases, sales, returns,
                            payments and transaction workflows.
                        </p>
                    </div>

                    {{-- Feature 3 --}}
                    <div
                        class="group rounded-2xl border border-white/10 bg-slate-900/60 p-6 transition hover:-translate-y-1 hover:border-violet-400/30 hover:bg-slate-900"
                    >
                        <div
                            class="flex size-12 items-center justify-center rounded-xl bg-violet-500/10 text-violet-400 transition group-hover:bg-violet-500 group-hover:text-white"
                        >
                            <svg
                                class="size-6"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.1a7.5 7.5 0 0 1 15 0A17.9 17.9 0 0 1 12 21.75a17.9 17.9 0 0 1-7.5-1.65Z"
                                />
                            </svg>
                        </div>

                        <h3 class="mt-5 text-lg font-bold text-white">
                            Contacts
                        </h3>

                        <p class="mt-3 text-sm leading-6 text-slate-400">
                            Organize customer and supplier profiles,
                            balances and complete transaction history.
                        </p>
                    </div>

                    {{-- Feature 4 --}}
                    <div
                        class="group rounded-2xl border border-white/10 bg-slate-900/60 p-6 transition hover:-translate-y-1 hover:border-emerald-400/30 hover:bg-slate-900"
                    >
                        <div
                            class="flex size-12 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-400 transition group-hover:bg-emerald-500 group-hover:text-white"
                        >
                            <svg
                                class="size-6"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"
                                />
                            </svg>
                        </div>

                        <h3 class="mt-5 text-lg font-bold text-white">
                            Reports & Insights
                        </h3>

                        <p class="mt-3 text-sm leading-6 text-slate-400">
                            Monitor sales, purchases, stock value,
                            profit and outstanding balances.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        {{-- CTA --}}
        <section
            id="benefits"
            class="px-5 py-20 sm:px-8 lg:px-12"
        >
            <div
                class="mx-auto max-w-[1400px] overflow-hidden rounded-[2rem] border border-blue-400/20 bg-gradient-to-br from-blue-600 to-blue-800 px-6 py-12 shadow-2xl shadow-blue-950/40 sm:px-10 lg:flex lg:items-center lg:justify-between lg:px-14"
            >
                <div class="max-w-3xl">
                    <p
                        class="text-xs font-bold uppercase tracking-[0.18em] text-blue-100"
                    >
                        Start Today
                    </p>

                    <h2
                        class="mt-3 text-3xl font-extrabold text-white sm:text-4xl"
                    >
                        Ready to Take Control of Your Inventory?
                    </h2>

                    <p
                        class="mt-4 text-base leading-7 text-blue-100"
                    >
                        Move from manual records to a modern, secure
                        and organized inventory management workflow.
                    </p>
                </div>

                <div class="mt-8 shrink-0 lg:mt-0">
                    @auth
                        <a
                            href="{{ route('dashboard') }}"
                            class="inline-flex min-h-13 items-center justify-center rounded-xl bg-white px-7 text-sm font-extrabold text-blue-700 shadow-xl transition hover:-translate-y-0.5 hover:bg-blue-50"
                        >
                            Open Dashboard
                        </a>
                    @else
                        @if (Route::has('register'))
                            <a
                                href="{{ route('register') }}"
                                class="inline-flex min-h-13 items-center justify-center rounded-xl bg-white px-7 text-sm font-extrabold text-blue-700 shadow-xl transition hover:-translate-y-0.5 hover:bg-blue-50"
                            >
                                Create Account
                            </a>
                        @endif
                    @endauth
                </div>
            </div>
        </section>
    </main>

    {{-- Footer --}}
    <footer
        class="relative z-10 border-t border-white/10 bg-slate-950"
    >
        <div
            class="mx-auto flex w-full max-w-[1500px] flex-col gap-4 px-5 py-8 text-sm text-slate-400 sm:flex-row sm:items-center sm:justify-between sm:px-8 lg:px-12"
        >
            <p>
                © {{ now()->year }} Smart Inventory Management System.
            </p>

            <p>
                Built for smarter business operations.
            </p>
        </div>
    </footer>

    {{-- Slider JavaScript --}}
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const slider = document.getElementById(
                'inventory-slider'
            );

            if (!slider) {
                return;
            }

            const slides = Array.from(
                slider.querySelectorAll('[data-slide]')
            );

            const dots = Array.from(
                slider.querySelectorAll('[data-slide-dot]')
            );

            const previousButton =
                document.getElementById('previous-slide');

            const nextButton =
                document.getElementById('next-slide');

            let currentSlide = 0;
            let autoplayTimer = null;

            const showSlide = (index) => {
                currentSlide =
                    (index + slides.length) % slides.length;

                slides.forEach((slide, slideIndex) => {
                    const isActive =
                        slideIndex === currentSlide;

                    slide.classList.toggle(
                        'visible',
                        isActive
                    );

                    slide.classList.toggle(
                        'invisible',
                        !isActive
                    );

                    slide.classList.toggle(
                        'translate-x-0',
                        isActive
                    );

                    slide.classList.toggle(
                        'translate-x-8',
                        !isActive
                    );

                    slide.classList.toggle(
                        'opacity-100',
                        isActive
                    );

                    slide.classList.toggle(
                        'opacity-0',
                        !isActive
                    );

                    slide.setAttribute(
                        'aria-hidden',
                        isActive ? 'false' : 'true'
                    );
                });

                dots.forEach((dot, dotIndex) => {
                    const isActive =
                        dotIndex === currentSlide;

                    dot.classList.toggle(
                        'w-8',
                        isActive
                    );

                    dot.classList.toggle(
                        'w-2.5',
                        !isActive
                    );

                    dot.classList.toggle(
                        'bg-blue-400',
                        isActive
                    );

                    dot.classList.toggle(
                        'bg-white/40',
                        !isActive
                    );
                });
            };

            const startAutoplay = () => {
                stopAutoplay();

                autoplayTimer = window.setInterval(
                    () => showSlide(currentSlide + 1),
                    5000
                );
            };

            const stopAutoplay = () => {
                if (autoplayTimer !== null) {
                    window.clearInterval(autoplayTimer);
                    autoplayTimer = null;
                }
            };

            previousButton?.addEventListener(
                'click',
                () => {
                    showSlide(currentSlide - 1);
                    startAutoplay();
                }
            );

            nextButton?.addEventListener(
                'click',
                () => {
                    showSlide(currentSlide + 1);
                    startAutoplay();
                }
            );

            dots.forEach((dot, index) => {
                dot.addEventListener('click', () => {
                    showSlide(index);
                    startAutoplay();
                });
            });

            slider.addEventListener(
                'mouseenter',
                stopAutoplay
            );

            slider.addEventListener(
                'mouseleave',
                startAutoplay
            );

            document.addEventListener(
                'visibilitychange',
                () => {
                    if (document.hidden) {
                        stopAutoplay();
                    } else {
                        startAutoplay();
                    }
                }
            );

            showSlide(0);
            startAutoplay();
        });
    </script>
</body>
</html>