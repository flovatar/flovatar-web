<x-guest-layout>
    <section class="tab-section">
        <div class="container">
            <div class="tab-frame">
                <div class="tab-content">
                    <div class="tab active">
                        <h1>{{ $title }}</h1>
                        <p>{{ $message }}</p>
                        <p><a href="{{ route('home') }}">Back to the homepage</a></p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-guest-layout>
