<x-chat-layout :flovatar=$flovatar :assistant=$assistant>

    <script>
        @if(isset($flovatar))
            window.flovatar = @json($flovatar);
        @endif

            @if(isset($templates))
            window.templates = @json($templates);
        @endif

            @if(isset($assistant))
            window.assistant = @json($assistant);
        @endif

            @if(isset($voices))
            window.voices = @json($voices);
        @endif

    </script>

    <div class="relative chat-container w-full border border-gray-300 dark:border-gray-700 m-auto md:border border-0 rounded-0 md:rounded-lg p-2 mt-0 md:mt-3 flex flex-col flex-1 overflow-auto mb-0 md:mb-5 xl:mb-9">



        <a href="/chat" class="absolute start-2 opacity-50 p-1 bg-black rounded-full z-30 z-50">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#fff" class="w-6 h-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
            </svg>
        </a>


        <div class="flex flex-1 justify-center shrink flovatar-img z-40">
            <div class="relative h-full aspect-square mt-1 overflow-visible">
                <div class="border-2 border-gray-600 absolute top-1 left-1/2 transform -translate-x-1/2 rounded-md min-w-full aspect-square overflow-hidden mx-auto flovatar-chat-svg z-40">
                    <div x-html="getFullSvg()"></div>
                    <button x-show="showEditBtn()" class="absolute top-1 left-1 opacity-50 p-1 bg-black rounded-full z-30" @click.prevent.stop="openEditor()">
                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.0" stroke="white" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        <div class="flex flex-8 grow flex-col mt-4 overflow-auto message-list z-10" id="message-list">
            <template x-for="message in messages()" :key="message.id">
                <div class="message" x-bind:class="message.type">
                    <span x-text="message.message"></span>
                    <template x-if="message.type == 'received' && message.id == messages()[messages().length - 1].id && audioReady">
                        <div class="flex cursor-pointer md:hidden my-1" @click.prevent.stop="playAudioPlayer();">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.91 11.672a.375.375 0 010 .656l-5.603 3.113a.375.375 0 01-.557-.328V8.887c0-.286.307-.466.557-.327l5.603 3.112z" />
                            </svg>
                            <span> Tap to listen</span>
                        </div>
                    </template>
                </div>
            </template>
            <template x-if="curStep == 1">
                <div class="message received">
                    <div class="flex">
                        <img class="spinner" src="/images/loader-white.svg" alt="">
                    </div>
                </div>
            </template>
            <template x-if="curStep == 0 && isThinking">
                <div class="message sent">
                    <span x-text="lastMessageSent"></span>
                </div>
            </template>
            <template x-if="curStep == 0 && messageList.length == 0 && !isThinking">
                <div class="italic text-xl md:text-2xl xl:text-3xl text-slate-500 p-8 mt-5 text-center intro-placeholder">
                    <span x-text="assistant.description"></span>
                </div>
            </template>
        </div>
        <div class="flex flex-none grow-0 mt-4">
            <input x-model="message" type="text" id="messageInput" class="flex-1 border rounded-full px-4 py-2 mr-2" placeholder="Type a message..." @keyup.enter="sendMessage()" >
            <button @click.prevent.stop="sendMessage()" x-bind:disabled="isThinking" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-full">Send</button>
        </div>
    </div>
</x-chat-layout>
