<x-chat-layout :flovatar=null :assistant=null>

    <script>
            @if(isset($assistants))
            window.assistants = @json($assistants);
        @endif


    </script>
    <div class="relative chat-container w-full border border-gray-300 dark:border-gray-700 m-auto md:border border-0 rounded-0 md:rounded-lg p-2 mt-0 md:mt-3 flex flex-col flex-1 overflow-auto mb-0 md:mb-5 xl:mb-9">
        <div class="grid grid-cols-1  md:grid-cols-2 2xl:grid-cols-3 gap-4 overflow-auto assistants-list pt-8">
            @foreach($assistants as $assistant)
            <div class="flovatar-chat-item relative">
                <a href="/chat/{{ isset($assistant->flovatar) && $assistant->flovatar->name ? $assistant->flovatar->name : $assistant->flow_id }}">
                    <div class="speech-bubble round float">{{ $assistant->description }}</div>
                    <img src="https://images.flovatar.com/flovatar/svg/{{ $assistant->flow_id }}-nobg.svg" alt="Flovatar image" />
                </a>
            </div>
            @endforeach
        </div>
    </div>
</x-chat-layout>
