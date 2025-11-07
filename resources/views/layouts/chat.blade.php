<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FlovaChat - Talk with {{ isset($flovatar) ? ($flovatar->name ? $flovatar->name : 'Flovatar #'.$flovatar->flow_id) : 'any Flovatar!' }}</title>

    <meta name="description" content="Talk with any Flovatar about anything!{{ isset($assistant) ? ' - '.$assistant->description : '' }}" />
    <link rel="canonical" href="https://flovatar.com/" />
    <meta property="og:locale" content="en_US" />
    <meta property="og:type" content="website" />
    <meta property="og:title" content="FlovaChat - {{ isset($flovatar) ? ($flovatar->name ? $flovatar->name : 'Flovatar #'.$flovatar->flow_id) : 'Talk with any Flovatar!' }}" />
    <meta property="og:description" content="Talk with any Flovatar about anything!{{ isset($assistant) ? ' - '.$assistant->description : '' }}" />
    <meta property="og:url" content="https://flovatar.com/" />
    <meta property="og:site_name" content="Flovatar" />
    <meta property="article:modified_time" content="2023-03-08T02:29:49+00:00" />
    <meta property="og:image" content="{{ isset($flovatar) ? 'https://images.flovatar.com/flovatar/png/'.$flovatar->flow_id.'.png' : 'https://flovatar.com/images/share.png' }}" />
    <meta property="og:image:width" content="{{ isset($flovatar) ? 480 : 1200 }}" />
    <meta property="og:image:height" content="{{ isset($flovatar) ? 480 : 630 }}" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:image" content="{{ isset($flovatar) ? 'https://images.flovatar.com/flovatar/png/'.$flovatar->flow_id.'.png' : 'https://flovatar.com/images/share.png' }}" />
    <meta name="twitter:site" content="@flovatar" />


    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    <style>
        .message-list{
            max-height: 99.9vh;
        }
        .flovatar-img{
            max-height: 50vh;
            min-height: 0.1vh;
        }
        .message {
            padding: 0.5rem 1rem;
            margin: 0.25rem;
            border-radius: 20px;
            max-width: 90%;
        }
        .sent {
            background-color: #e5e5e5;
            align-self: flex-end;
        }
        .received {
            background-color: #007aff;
            color: white;
            align-self: flex-start;
        }
        .dark .message.sent {
            background-color: #4a4a4a;
            color: white;
        }
        .chat-container {
            background-color: white;
        }
        .dark .chat-container {
            background-color: #2a2a2a;
        }

        .earth {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            z-index: -1;
            height: 100vh;
        }
        .stars {
            position: absolute;
            left: 0;
            top: 88px;
            z-index: -1;
        }
        .stars.right {
            left: auto;
            right: 0;
        }
        .earth-wrapper {
            display: flex;
            height: 100vh;
            overflow-y: hidden;
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            z-index: -1;
        }
        .earth img {
            width: 100%;
            position: absolute;
            bottom: -20%;
        }
        body {
            background-color: #1a083a !important;
            position: relative;
        }
        .chat-container {
            background-color: rgba(255,255,255,0.85);
            margin: 0 !important;
            border: 3px solid black !important;
        }
        .chat-header {

        }


        .chat-device-frame {
            display: flex;
            border-radius: 40px;
            background-color: #92a09f;
            border: 3px solid black;
            overflow: hidden;
            padding: 10px 56px;
            margin: 0 auto;
            width: 60%;
            min-width: 480px;
            height: calc(100vh - 120px);
            position: relative;
            box-shadow: inset 0 0 10px 10px rgba(0,0,0,.2);
        }
        .chat-controls-left {
            display: flex;
            width: 50px;
            height: 100%;
            position: absolute;
            top: 0;
            left: 3px;
            z-index: 5;
            align-items: center;
        }
        .chat-controls-right {
            display: flex;
            width: 50px;
            height: 100%;
            position: absolute;
            top: 0;
            right: 3px;
            z-index: 5;
            align-items: center;
        }
        .chat-wrapper {
            display: flex;
            justify-content: center;
            width: 100%;
            overflow-x: hidden;
        }

        @media screen and (max-width: 480px) {
            .chat-device-frame {
                width: calc(100vw + 112px);
            }
        }
        .social-share-button {
            position: absolute;
            top: 5px;
            right: 5px;
            height: 30px;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            z-index: 50;
        }
        .social-share-button svg {
            width: 30px;
            height: 30px;
            cursor: pointer;
            margin: 0 5px;
            color: #000;
        }
        .dark .social-share-button svg {
            color: #fff;
        }

        .social-footer{
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: rgba(42,42,42,0.7);
            z-index: 100;
            padding-bottom: 5px;
            box-shadow: 0 2px 3px rgba(1,1,1,.1);
            backdrop-filter: blur(3px);
        }

        .social-footer button {
            margin: 0 4px;
        }
        .social-footer img,
        .social-footer svg {
            width: 24px;
            height: 24px;
            cursor: pointer;
            margin: 0 4px;
            color: #000;
        }

        .dark .social-footer svg {
            color: #fff;
        }





        @font-face {
            font-family: 'SequentialistBB';
            src: url('https://s3-us-west-2.amazonaws.com/s.cdpn.io/4273/306FA6_1_0.woff2') format('woff2'),
            url('https://s3-us-west-2.amazonaws.com/s.cdpn.io/4273/306FA6_0_0.woff') format('woff');
            font-style: normal;
            font-weight: 400;
        }
        @font-face {
            font-family: 'SequentialistBB';
            src: url('https://s3-us-west-2.amazonaws.com/s.cdpn.io/4273/306FA6_0_0.woff2') format('woff2'),
            url('https://s3-us-west-2.amazonaws.com/s.cdpn.io/4273/306FA6_0_0.woff') format('woff');
            font-style: italic;
            font-weight: 400;
        }
        blockquote.bubble {
            background-position: center;
            background-repeat: no-repeat !important;
            background-size: 100% 100%;
            margin: 0 auto;
            text-align: center;
            box-sizing: content-box;
            line-height: 1;
            font-family: SequentialistBB, cursive;
            font-size: 1.1em;
            width: 80%;
        }
        blockquote.speech {
            background: url(https://s3-us-west-2.amazonaws.com/s.cdpn.io/4273/speech-bubble.svg);
            padding-top: 19%;
            padding-bottom: 45%;
        }
        blockquote.whisper {
            background: url(https://s3-us-west-2.amazonaws.com/s.cdpn.io/4273/whisper.svg);
            font-size: 2vw;
            color: #ccc;
            font-style: italic;
            padding: 6% 5% 15%;
        }
        blockquote.electric {
            background: url(https://s3-us-west-2.amazonaws.com/s.cdpn.io/4273/electric.svg);
            font-size: 2.4vw;
            font-style: italic;
            padding: 4% 6% 12% 0%;
        }
        blockquote.electric span {
            display: block;
            font-size: 3vw;
            font-weight: bold;
        }

        .assistants-list {

        }
        .flovatar-chat-item {
        }
        .flovatar-chat-item blockquote {
        }
        .flovatar-chat-item img {

        }

        .modal {
            display: none;
        }










        .speech-bubble{
            --arrow-w:0.5em;
            --arrow-h:1em;

        }
        .speech-bubble{
            text-align:center;
            line-height: 1;
            font-family: SequentialistBB, cursive;
            font-size: 1em;
            color: black;
            background: white;
            padding:1em;
            position:relative;
            border-radius: 1em;
            max-width: 90%;
            box-shadow:0 0 0 3px black;
            margin: 5px auto -30px auto;
        }


        .speech-bubble:before,.speech-bubble:after{
            content:"";
            position:absolute;
            bottom:calc(-1 * var(--arrow-h) * 2 + 0.5px);
            left:70%;
            border-style:solid;
            border-width:var(--arrow-h) var(--arrow-w);
            border-color: white transparent transparent;
        }

        .speech-bubble:before{
            border-width:calc(var(--arrow-h) + 3px) calc(var(--arrow-w) + 3px);
            border-color:black transparent transparent;
            bottom:calc(-1 * var(--arrow-h) * 2 - 2.5*3px);
            left:calc(70% - 3px);
        }


        .speech-bubble.pop{
            animation-name: pop;
            animation-duration: 0.5s;
            animation-timing-function:cubic-bezier(0.755, 0.050, 0.855, 0.060);
        }

        .flovatar-chat-item:hover .speech-bubble.float{
            animation-name: float-down;
            animation-duration: 2s;
            animation-iteration-count: infinite;
            animation-timing-function:ease;
        }


        @keyframes pop {
            0% {transform: scale(0.8);}
            80% {transform: scale(1.1);}
            90% {transform: scale(0.9);}
            100% {transform: scale(1);}
        }

        @keyframes float-up {
            0% {transform: translateY(0);}
            50% {transform: translateY(calc(-1 * var(--arrow-h)));}
        }

        @keyframes float-left {
            0% {transform: translateX(0);}
            50% {transform: translateX(calc(-1 * var(--arrow-h)));}
        }

        @keyframes float-right {
            0% {transform: translateX(0);}
            50% {transform: translateX(var(--arrow-h));}
        }

        @keyframes float-down {
            0% {transform: translateY(0);}
            50% {transform: translateY(var(--arrow-h));}
        }


        @keyframes rotation {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .spinner {
            width: 20px;
            height: 20px;
            opacity: 0.7;
            animation: rotation 1.5s infinite linear;
        }

        .flovatar-chat-svg {
            min-height: 150px;
            background-color: rgba(255,255,255,0.5);
            backdrop-filter: blur(3px);
        }

        .intro-placeholder {
            font-family: SequentialistBB, cursive;
        }



    </style>


    <!-- Scripts -->
    <script>
        var svgOpen = '{!! config('flovatar.svgOpen') !!}';
        var svgClose = '{!! config('flovatar.svgClose') !!}';
    </script>
    <script src="{{ mix('js/app.js') }}" defer></script>
    <script defer data-domain="flovatar.com" src="https://plausible.io/js/plausible.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sharer.js@latest/sharer.min.js"></script>
</head>
<body class="bg-gray-100 dark:bg-gray-800 transition-colors duration-300">

<div class="earth-wrapper">
    <div class="stars left">
        <img src="/images/stars-l.png" width="307" alt="">
    </div>
    <div class="stars right">
        <img src="/images/stars-r.png" width="364" alt="">
    </div>
    <div class="earth">
        <img src="/images/earth-cresent.png" alt="">
    </div>
</div>

<div
    x-data="appData"
    x-init="initApp($dispatch); jsLoaded = true;"
    @user-check-finish.window="userCheckLoading = false"
    @user-loaded.window="loadLoggedUser($event.detail.user)"
    @profile-loaded.window="profile = $event.detail.profile; userCheckLoading = false;"
    @logged-out.window="user = null; profile = null; currentWallet = null;"
    @loading-start.window="loading = true; waitingSeal = false;"
    @waiting-seal.window="loading = false; waitingSeal = true;"
    @loading-completed.window="loading = false; waitingSeal = false; sealCompleted = true; setTimeout(()=>sealCompleted=false,3000);"
    @transaction-error.window="handleTransactionErrorMessage($event.detail.message)"
    @address-found.window="handleAddressFound($event.detail.profile)"
    @login-failed.window="showLoginResult = true"
>


    <div
        class="flex flex-col h-screen"
        x-data="chatData(window.flovatar, window.templates, window.assistant, window.voices)"
        x-init="initChat($dispatch);"
        @flovatar-loaded.window="loadFlovatarFromChain($event.detail.flovatar)"
        @flovatar-sale-loaded.window="loadSaleFromChain($event.detail.sale)"
    >

        <div class="chat-header text-gray-300 relative flex justify-between items-center w-full m-0 p-3 md:p-4 xl:p-6 2xl:p-8">
            <div class="logo text-pink-500 text-2xl font-bold h-8">
                <a href="/"><img class="w-auto h-full" src="/images/flovatar-logo-pink.svg" alt="Flovatar"></a>
            </div>
            <div class="mr-8 cursor-pointer" @click.prevent.stop="showSocial = true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 8.25H7.5a2.25 2.25 0 00-2.25 2.25v9a2.25 2.25 0 002.25 2.25h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25H15m0-3l-3-3m0 0l-3 3m3-3V15" />
                </svg>
            </div>
            <div class="flex">
                <label for="dark-mode-toggle" class="flex items-center cursor-pointer" @click.prevent.default="toggleDarkMode()">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 mr-2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                    </svg>

                    <button type="button" x-bind:class="darkMode ? 'bg-indigo-600' : 'bg-gray-200'" class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2" role="switch" aria-checked="false">
                        <span class="sr-only">Dark Mode</span>
                        <span aria-hidden="true" x-bind:class="darkMode ? 'translate-x-5' : 'translate-x-0'" class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                    </button>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 ml-2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                    </svg>
                </label>
            </div>
            <div class="social-footer" x-show="showSocial">
                <button data-sharer="twitter" data-title="FlovaChat - {{ isset($assistant) ? $assistant->description : 'Talk with any Flovatar about anything!' }}" data-hashtags="Flovatar FlovaChat " data-url="{{ url()->current() }}"><img x-bind:src="'/images/social/x.svg'" /></button>
                <button data-sharer="reddit" data-title="FlovaChat - {{ isset($assistant) ? $assistant->description : 'Talk with any Flovatar about anything!' }}" data-hashtags="Flovatar FlovaChat " data-url="{{ url()->current() }}"><img x-bind:src="'/images/social/reddit.svg'" /></button>
                <button data-sharer="telegram" data-title="FlovaChat - {{ isset($assistant) ? $assistant->description : 'Talk with any Flovatar about anything!' }}" data-hashtags="Flovatar FlovaChat " data-url="{{ url()->current() }}"><img x-bind:src="'/images/social/telegram.svg'" /></button>
                <button data-sharer="hackernews" data-title="FlovaChat - {{ isset($assistant) ? $assistant->description : 'Talk with any Flovatar about anything!' }}" data-hashtags="Flovatar FlovaChat " data-url="{{ url()->current() }}"><img x-bind:src="'/images/social/ycombinator.svg'" /></button>
                <button data-sharer="whatsapp" data-title="FlovaChat - {{ isset($assistant) ? $assistant->description : 'Talk with any Flovatar about anything!' }}" data-hashtags="Flovatar FlovaChat " data-url="{{ url()->current() }}"><img x-bind:src="'/images/social/whatsapp.svg'" /></button>
                <button data-sharer="facebook" data-title="FlovaChat - {{ isset($assistant) ? $assistant->description : 'Talk with any Flovatar about anything!' }}" data-hashtags="Flovatar FlovaChat " data-url="{{ url()->current() }}"><img x-bind:src="'/images/social/facebook.svg'" /></button>
                <button data-sharer="linkedin" data-title="FlovaChat - {{ isset($assistant) ? $assistant->description : 'Talk with any Flovatar about anything!' }}" data-hashtags="Flovatar FlovaChat " data-url="{{ url()->current() }}"><img x-bind:src="'/images/social/linkedin.svg'" /></button>
                <button data-sharer="baidu" data-title="FlovaChat - {{ isset($assistant) ? $assistant->description : 'Talk with any Flovatar about anything!' }}" data-hashtags="Flovatar FlovaChat " data-url="{{ url()->current() }}"><img x-bind:src="'/images/social/baidu.svg'" /></button>
                <button data-sharer="weibo" data-title="FlovaChat - {{ isset($assistant) ? $assistant->description : 'Talk with any Flovatar about anything!' }}" data-hashtags="Flovatar FlovaChat " data-url="{{ url()->current() }}"><img x-bind:src="'/images/social/sinaweibo.svg'" /></button>
                <button data-sharer="email" data-title="FlovaChat - {{ isset($assistant) ? $assistant->description : 'Talk with any Flovatar about anything!' }}" data-hashtags="Flovatar FlovaChat " data-url="{{ url()->current() }}"><img x-bind:src="'/images/social/gmail.svg'" /></button>
            </div>
        </div>

        <div class="chat-wrapper" @click="showSocial = false">
            <div class="chat-device-frame">
                <div class="chat-controls-left">
                    <img src="/images/controls-left.svg" alt="" />
                </div>


                {{ $slot }}



                <div class="chat-controls-right">
                    <img src="/images/controls-right.svg" alt="" />
                </div>
            </div>
        </div>

        <template x-if="assistant && flovatar">
        <!-- Modal popup with fields to edit the Instructions (text area) and a select dropdown for the voices -->
        <div class="modal fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 overflow-y-auto" x-show="showModal">
            <div class="modal-overlay absolute w-full h-full bg-gray-900 opacity-50"></div>

            <div class="modal-container bg-white w-11/12 md:max-w-xl mx-auto rounded shadow-lg z-50 overflow-y-auto">

                <div class="modal-content py-4 text-left px-6">

                    <div class="flex justify-between items-center pb-3">
                        <p class="text-2xl font-bold">Edit Flovatar</p>
                        <div class="modal-close cursor-pointer z-50" @click.prevent.stop="showModal = false">
                            <svg class="fill-current text-black" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18">
                                <path d="M1 1l16 16m0-16L1 17" />
                            </svg>
                        </div>
                    </div>


                    <div class="rounded-md bg-yellow-50 p-4" x-show="!editEnabled">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-yellow-800">Burn 5,000 ÐUST to unlock editing feature</h3>
                                <div class="mt-2 text-sm text-yellow-700">
                                    <p>Every Flovatar comes with a default voice and knowledge defined by the NFT metadata. Burn 5,000 ÐUST to unlock the full potentiality and customization capabilities.</p>
                                </div>
                                <button @click.prevent.stop="enableChatEdit()" type="button" class="mt-3 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Unlock Feature</button>
                            </div>
                        </div>
                    </div>

                    <div class="my-5">
                        <label class="pb-5">Description <span class="text-sm">(public)</span></label>
                        <textarea x-bind:disabled="!editEnabled" x-model="assistant.description" class="w-full px-3 py-2 text-gray-700 border rounded-lg focus:outline-none" rows="4" placeholder="Enter description here" maxlength="140"></textarea>
                    </div>

                    <div class="my-5">
                        <label class="pb-5">Instructions <span class="text-sm">(private)</span></label>
                        <textarea x-bind:disabled="!editEnabled" x-model="assistant.user_instruction" class="w-full px-3 py-2 text-gray-700 border rounded-lg focus:outline-none" rows="4" placeholder="Enter instructions here" maxlength="20000"></textarea>
                    </div>

                    <div class="my-5">
                        <label class="pb-5">Voice </label>

                        <select x-bind:disabled="!editEnabled" x-model="assistant.voice_id" class="w-full px-3 py-2 text-gray-700 border rounded-lg focus:outline-none">
                            <template x-for="voice in voices" :key="voice.voice_id">
                                <option x-bind:value="voice.voice_id" :selected="voice.voice_id === assistant.voice_id" x-text="voice.name"></option>
                            </template>
                        </select>
                        <button @click.prevent.stop="playVoice()" class="m-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.91 11.672a.375.375 0 010 .656l-5.603 3.113a.375.375 0 01-.557-.328V8.887c0-.286.307-.466.557-.327l5.603 3.112z" />
                            </svg>
                        </button>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button @click.prevent.stop="showModal = false" class="px-4 bg-transparent p-3 rounded-lg text-indigo-500 hover:bg-gray-100 hover:text-indigo-400 mr-2">Cancel</button>
                        <button x-bind:disabled="!editEnabled"  @click.prevent.stop="saveAssistant()" class="px-4 bg-indigo-500 p-3 rounded-lg text-white hover:bg-indigo-400">Save</button>
                    </div>
                </div>
            </div>
        </div>
        </template>

    </div>


</div>
</div>

</body>
</html>
