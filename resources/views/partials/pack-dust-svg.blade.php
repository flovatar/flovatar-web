<svg id="pack-opening-dust" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 1920 1000">
    <g id="dust-flobit-pack">
        <defs>

            <style>
                svg {
                    background-color: transparent;
                }

                #pack-wrapper {

                }

                @keyframes flash-light {
                    0% { opacity: 0 }
                    20% { opacity: 0 }
                    30% { opacity: 1 }
                    60% { opacity: 1 }
                    70% { opacity: 0 }
                    100% { opacity: 0 }
                }
                .pack-light {
                    animation-iteration-count: infinite;
                    transform-origin: center;
                    transform-box: fill-box;
                    animation-timing-function: linear;
                }
                #pack-light2 {
                    animation-name: flash-light;
                    animation-duration: .5s;
                }
                #pack-light3 {
                    animation-name: flash-light;
                    animation-duration: .5s;
                    animation-delay: .25s;
                }

                @keyframes speck-rotate {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }

                @keyframes dust-sparkle {
                    0% { opacity: 0; transform: rotate(0deg) }
                    50% { opacity: 1; transform: rotate(180deg) }
                    100% { opacity: 0; transform: rotate(360deg) }
                }
                .speck-wrapper {
                    animation-name: dust-sparkle;
                    animation-iteration-count: infinite;
                    transform-origin: -300px 90px;
                    transform-box: fill-box;
                    animation-timing-function: linear;
                }
                .speck {
                    animation-iteration-count: infinite;
                    transform-origin: 15px 30px;
                    transform-box: fill-box;
                    animation-timing-function: linear;
                }
                #speck1 {
                    animation-name: dust-sparkle;
                    animation-duration: .8s;
                }
                #speck2 {
                    animation-name: dust-sparkle;
                    animation-duration: .7s;
                    animation-direction: reverse;
                }
                #speck3 {
                    animation-name: speck-rotate;
                    animation-duration: .75s;
                }
                #speck4 {
                    animation-name: dust-sparkle;
                    animation-duration: .9s;
                }
                #speck5 {
                    animation-name: dust-sparkle;
                    animation-duration: .65s;
                    animation-direction: reverse;
                }
                #speck6 {
                    animation-name: dust-sparkle;
                    animation-duration: .8s;
                    animation-direction: reverse;
                }
                #speck7 {
                    animation-name: dust-sparkle;
                    animation-duration: 1s;
                }
                #speck8 {
                    animation-name: dust-sparkle;
                    animation-duration: 1.1s;
                }
                #speck9 {
                    animation-name: dust-sparkle;
                    animation-duration: 1.2s;
                    animation-direction: reverse;
                }
                #speck10 {
                    animation-name: dust-sparkle;
                    animation-duration: .8s;
                }

                @keyframes fade-out {
                    0% { opacity: 1 }
                    100% { opacity: 0 }
                }

                @keyframes shake {
                    0% { transform: rotate(-3deg) }
                    50% { transform: rotate(3deg) }
                    100% { transform: rotate(-3deg) }
                }
                #pack-wrapper.loading #pack {
                    animation-name: shake;
                    animation-duration: .1s;
                    animation-iteration-count: infinite;
                    transform-origin: center;
                    transform-box: fill-box;
                }

                @keyframes blow-side {
                    0% {  }
                    100% { transform: translate(-9000px, -20px) rotate(-45deg) scale(3)  }
                }
                #pack-wrapper.open #pack-side {
                    animation-name: blow-side;
                    animation-fill-mode: forwards;
                    animation-duration: .5s;
                    animation-iteration-count: 1;
                    transform-origin: center;
                    transform-box: fill-box;
                }

                @keyframes blow-front {
                    0% {  }
                    100% { transform: translate(9000px, -20px) rotate(-45deg) scale(3) }
                }
                #pack-wrapper.open #pack-front {
                    animation-name: blow-front;
                    animation-fill-mode: forwards;
                    animation-duration: .5s;
                    animation-iteration-count: 1;
                    transform-origin: center;
                    transform-box: fill-box;
                }

                @keyframes blow-top {
                    0% {  }
                    100% { transform: translate(0px, -9000px) rotate(-45deg) scale(3) }
                }
                #pack-wrapper.open #pack-top {
                    animation-name: blow-top;
                    animation-fill-mode: forwards;
                    animation-duration: .5s;
                    animation-iteration-count: 1;
                    transform-origin: center;
                    transform-box: fill-box;
                }


                #pack-wrapper.open #dust {
                    animation-name: fade-out;
                    animation-fill-mode: forwards;
                    animation-duration: 1s;
                    animation-iteration-count: 1;
                    transform-origin: center;
                    transform-box: fill-box;
                }


                #glow {
                    transform-origin: center;
                    transform: scale(.01);
                }

                @keyframes blast {
                    0% { opacity: 1 }
                    40% { transform: scale(30) }
                    41% { transform: scale(30); opacity: 1 }
                    100% { transform: scale(30); opacity: 0 }
                }
                #pack-wrapper.open #glow {
                    animation-name: blast;
                    animation-fill-mode: forwards;
                    animation-duration: 2s;
                    animation-iteration-count: 1;
                    transform-origin: center;
                    transform-box: fill-box;
                }




            </style>

            <linearGradient id="linear-gradient-pack" x1="833.84" y1="523.96" x2="1145.93" y2="523.96" gradientUnits="userSpaceOnUse">
                <stop offset="0" stop-color="#aaf5f2"/>
                <stop offset="1" stop-color="#e9ea75"/>
            </linearGradient>

            <radialGradient id="radial-gradient" cx="925.74" cy="525.93" fx="925.74" fy="525.93" r="1223.15" gradientUnits="userSpaceOnUse">
                <stop offset="0" stop-color="#fff"/>
                <stop offset=".12" stop-color="#fff" stop-opacity=".98"/>
                <stop offset=".25" stop-color="#fff" stop-opacity=".94"/>
                <stop offset=".37" stop-color="#fff" stop-opacity=".85"/>
                <stop offset=".5" stop-color="#fff" stop-opacity=".74"/>
                <stop offset=".63" stop-color="#fff" stop-opacity=".6"/>
                <stop offset=".76" stop-color="#fff" stop-opacity=".42"/>
                <stop offset=".89" stop-color="#fff" stop-opacity=".21"/>
                <stop offset="1" stop-color="#fff" stop-opacity="0"/>
            </radialGradient>
        </defs>

        <g id="pack-wrapper" x-bind:class="dustPackWrapperClass">
            <circle id="glow" cx="925.74" cy="525.93" r="1223.15" style="fill: url(#radial-gradient);"/>

            <g id="pack">
                <g id="pack-top">
                    <path d="m661.31,314.09s1.5-13.38,19.93-17.72c18.82-4.43,108.88-26.94,108.88-26.94,0,0,19.11-6.17,43.72-3.22,24.61,2.95,314.68,20.56,314.68,20.56,0,0,22.52,6.27,22.15,25.1,0,0-100.6,13.39-114.38,13.39s-397.64,2.85-397.64,2.85c0,0-.57-9.33,2.67-14.03Z" style="fill: #47427c; stroke: #000; stroke-miterlimit: 10; stroke-width: 2.83px;"/>
                </g>
                <g id="pack-side">
                    <g>
                        <g>
                            <path d="m763.06,540.88v46.47l-.22.87c-20.65-5.24-57.86-14.63-79.5-19.91v-42.88c21.72,4.54,59.12,10.36,79.73,14.83v.61Z" style="fill: #beb4f4;"/>
                            <path d="m763.06,587.34v-47.08c-20.6-4.48-58-10.29-79.73-14.83v42.88c21.65,5.28,58.85,14.68,79.5,19.91l.22-.87Zm8.78,79.71v39.13l-97.44-35.43v-323.82l97.44-20.67v340.8Zm-8.78-2.71v-39.11c-22.21-5.98-58.52-15.73-79.73-21.23v36.03c21.21,6.3,57.51,17.46,79.73,24.32Zm0-168.83v-47.16c-20.6-1.31-58-3.67-79.73-4.99v41.46c21.72,2.84,59.12,7.9,79.73,10.7Zm0-88.16v-40.99c-20.6,2.42-58,6.81-79.73,9.27v33.86c21.72-.57,59.12-1.58,79.73-2.14Z" style="fill: #d3eea0;"/>
                            <path d="m801.47,266.17l.07.27c-8.34,2.02-16.91,7.17-16.91,19.82v458.37s-1.63,17.85,14.43,24.64v.03c-16.78-7.14-78.49-33.3-107.93-44.43-36.42-13.78-32.48-37.4-32.48-37.4v-357.29c0-26.58,11.81-31.5,11.81-31.5l131.02-32.51Zm-29.64,440v-379.93l-97.44,20.67v323.82l97.44,35.43Z" style="fill: #d6d2f9;"/>
                            <path d="m763.06,625.23v39.11c-22.21-6.85-58.52-18.02-79.73-24.32v-36.03c21.21,5.5,57.51,15.25,79.73,21.23Z" style="fill: #beb4f4;"/>
                            <path d="m763.06,448.35v47.16c-20.6-2.8-58-7.86-79.73-10.7v-41.46c21.72,1.32,59.12,3.69,79.73,4.99Z" style="fill: #beb4f4;"/>
                            <path d="m763.06,366.36v40.99c-20.6.56-58,1.57-79.73,2.14v-33.86c21.72-2.46,59.12-6.85,79.73-9.27Z" style="fill: #beb4f4;"/>
                        </g>
                        <g>
                            <line x1="799.04" y1="769.31" x2="799.06" y2="769.28" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <line x1="801.47" y1="266.17" x2="801.54" y2="266.45" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <path d="m801.47,266.17l-131.02,32.51s-11.81,4.92-11.81,31.5v357.29s-3.94,23.62,32.48,37.4c29.44,11.14,91.15,37.3,107.92,44.43" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <line x1="674.39" y1="601.7" x2="674.39" y2="566.15" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <line x1="674.39" y1="376.64" x2="674.39" y2="409.73" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <line x1="771.83" y1="627.59" x2="771.83" y2="590.5" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <line x1="771.83" y1="365.33" x2="771.83" y2="407.12" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <polyline points="674.39 637.4 674.39 670.74 771.83 706.18 771.83 667.05" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <line x1="771.83" y1="448.91" x2="771.83" y2="407.12" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <line x1="674.39" y1="409.73" x2="674.39" y2="442.82" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <line x1="674.39" y1="637.4" x2="674.39" y2="601.7" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <line x1="771.83" y1="667.05" x2="771.83" y2="627.59" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <line x1="771.83" y1="544.45" x2="771.83" y2="496.71" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <polyline points="771.83 365.33 771.83 326.25 674.39 346.92 674.39 376.64" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <line x1="674.39" y1="483.66" x2="674.39" y2="523.58" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <line x1="771.83" y1="496.71" x2="771.83" y2="448.91" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <line x1="771.83" y1="590.5" x2="771.83" y2="544.45" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <line x1="674.39" y1="442.82" x2="674.39" y2="483.66" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <line x1="674.39" y1="523.58" x2="674.39" y2="566.15" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <path d="m683.33,375.64c21.72-2.46,59.12-6.85,79.73-9.27" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <path d="m763.06,407.36c-20.6.56-58,1.57-79.73,2.14" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <path d="m763.06,448.35c-20.6-1.31-58-3.67-79.73-4.99" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <path d="m763.06,495.51c-20.6-2.8-58-7.86-79.73-10.7" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <path d="m763.06,540.26c-20.6-4.48-58-10.29-79.73-14.83" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <path d="m683.33,568.3c21.65,5.28,58.85,14.68,79.5,19.91.07.02.15.04.22.06" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <path d="m683.33,603.99c21.21,5.5,57.51,15.25,79.73,21.23" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <path d="m683.33,640.02c21.21,6.3,57.51,17.46,79.73,24.32" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <line x1="683.33" y1="443.36" x2="683.33" y2="484.82" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <line x1="763.06" y1="625.23" x2="763.06" y2="664.34" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <line x1="683.33" y1="640.02" x2="683.33" y2="603.99" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <line x1="683.33" y1="525.43" x2="683.33" y2="568.3" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <line x1="763.06" y1="366.36" x2="763.06" y2="407.36" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <line x1="683.33" y1="375.64" x2="683.33" y2="409.5" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <line x1="763.06" y1="495.51" x2="763.06" y2="448.35" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <line x1="763.06" y1="587.34" x2="763.06" y2="540.88" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                        </g>
                    </g>
                </g>
                <g id="pack-front">
                    <g>
                        <g>
                            <path d="m819.94,265.62c9.85.64,44.33,2.87,87.46,5.63l21.42,14.16,30.03,1.83c.19,4.88,6.96,21.32,20.03,25.53,14.94,4.82,48.94,2.68,48.94,2.68,0,0,8.54-3.53,12.63-23.24l25.67,1.56c.12-4.5-.14-8.71-.71-12.64,34.09,2.06,61.79,3.65,74.53,4.19,36.42,1.56,32.48,35.99,32.48,35.99v382.61c0,30.3-29.53,31.25-29.53,31.25,0,0-274.82,29.57-319.89,35.99-41.34,5.89-38.38-26.52-38.38-26.52v-458.37c0-24.62,32.48-20.84,32.48-20.84,0,0,.98.06,2.84.18Zm325.89,99.68s3.17-27.7-26.13-28.95c-39.67-1.7-259.7-16-259.7-16,0,0-26.13-3.05-26.13,16.76v368.72s-2.37,26.07,30.88,21.33c36.25-5.16,257.32-28.95,257.32-28.95,0,0,23.75-.76,23.75-25.14v-307.77Z" style="fill: #afa9ea;"/>
                            <path d="m1119.7,336.35c29.3,1.25,26.13,28.95,26.13,28.95v307.77c0,24.38-23.75,25.14-23.75,25.14,0,0-221.07,23.79-257.32,28.95-33.25,4.73-30.88-21.33-30.88-21.33v-368.72c0-19.81,26.13-16.76,26.13-16.76,0,0,220.03,14.3,259.7,16Zm12.65,319.59c.3-14.4-8.85-26.07-20.45-26.07s-21.24,11.67-21.55,26.07c-.3,14.4,8.85,26.07,20.45,26.07s21.25-11.67,21.55-26.07Zm-32.76-145.14c0-55.39-37.9-100.91-86.35-106.05-7.36-2.01-15.04-3.07-22.95-3.07-53.48,0-96.83,48.62-96.83,108.59s43.35,108.59,96.83,108.59c5.64,0,11.16-.54,16.53-1.58,51.48-1.62,92.77-48.67,92.77-106.49Zm-183.21-130.69c0-20.49-14.62-37.1-32.65-37.1s-32.65,16.61-32.65,37.1,14.62,37.1,32.65,37.1,32.65-16.61,32.65-37.1Zm-17.84,227.72c0-15.47-11.04-28.02-24.65-28.02s-24.65,12.54-24.65,28.02,11.04,28.01,24.65,28.01,24.65-12.54,24.65-28.01Zm0,69.35c0-15.47-11.04-28.01-24.65-28.01s-24.65,12.54-24.65,28.01,11.04,28.01,24.65,28.01,24.65-12.54,24.65-28.01Z" style="fill: url(#linear-gradient-pack);"/>
                            <path d="m1111.9,629.86c11.6,0,20.76,11.67,20.45,26.07s-9.95,26.07-21.55,26.07-20.75-11.67-20.45-26.07c.3-14.4,9.95-26.07,21.55-26.07Zm12.37,26.07c.19-8.86-5.45-16.04-12.58-16.04s-13.07,7.18-13.26,16.04c-.19,8.86,5.45,16.04,12.58,16.04s13.07-7.18,13.26-16.04Z" style="fill: #8080ad;"/>
                            <path d="m1111.69,639.89c7.14,0,12.77,7.18,12.58,16.04-.19,8.86-6.12,16.04-13.26,16.04s-12.77-7.18-12.58-16.04c.19-8.86,6.12-16.04,13.26-16.04Z" style="fill: #1b1344;"/>
                            <path d="m1013.24,404.75c48.45,5.14,86.35,50.66,86.35,106.05s-41.29,104.87-92.77,106.49c-.91.03-1.82.04-2.74.04-52.75,0-95.51-47.7-95.51-106.53s42.76-106.53,95.51-106.53c3.09,0,6.14.17,9.15.49Zm59.45,105.06c0-37.78-27.46-68.41-61.33-68.41-3.73,0-7.37.37-10.92,1.08-28.66,5.74-50.41,33.7-50.41,67.33s21.75,61.58,50.41,67.32c3.54.71,7.19,1.08,10.92,1.08,33.87,0,61.33-30.63,61.33-68.41Z" style="fill: #8080ad;"/>
                            <path d="m1040.45,292.2c-4.09,19.71-12.63,23.24-12.63,23.24,0,0-33.99,2.14-48.94-2.68-13.07-4.21-19.84-20.65-20.03-25.53l-30.03-1.83-21.42-14.16c-43.13-2.75-77.61-4.99-87.46-5.63-.51-3.19-1.36-18.32,27.68-20.08,32.48-1.97,86.62-10.83,105.31-9.84,18.7.98,56.67,4.43,63.81,4.94,31,2.21,44.35,10.84,48.67,40.49.57,3.93.83,8.14.71,12.64l-25.67-1.56Z" style="fill: #7aa2f8;"/>
                            <path d="m1011.36,441.41c33.87,0,61.33,30.63,61.33,68.41s-27.46,68.41-61.33,68.41c-3.73,0-7.37-.37-10.92-1.08,28.66-5.74,50.41-33.7,50.41-67.32s-21.75-61.58-50.41-67.33c3.54-.71,7.19-1.08,10.92-1.08Z" style="fill: #3e3e60;"/>
                            <path d="m1000.45,442.49c28.66,5.74,50.41,33.7,50.41,67.33s-21.75,61.58-50.41,67.32c-28.66-5.74-50.41-33.7-50.41-67.32s21.75-61.58,50.41-67.33Z" style="fill: #00ffc2;"/>
                            <path d="m1013.24,404.75c-3.01-.32-6.07-.49-9.15-.49-52.75,0-95.51,47.7-95.51,106.53s42.76,106.53,95.51,106.53c.92,0,1.83-.02,2.74-.04-5.37,1.03-10.9,1.58-16.53,1.58-53.48,0-96.83-48.62-96.83-108.59s43.35-108.59,96.83-108.59c7.91,0,15.59,1.06,22.95,3.07Z" style="fill: #3e3e60;"/>
                            <path d="m883.73,343.01c18.03,0,32.65,16.61,32.65,37.1s-14.62,37.1-32.65,37.1-32.65-16.61-32.65-37.1,14.62-37.1,32.65-37.1Zm20.09,37.1c0-12.61-8.99-22.83-20.09-22.83s-20.09,10.22-20.09,22.83,8.99,22.83,20.09,22.83,20.09-10.22,20.09-22.83Z" style="fill: #8080ad;"/>
                            <ellipse cx="883.73" cy="380.11" rx="20.09" ry="22.83" style="fill: #1b1344;"/>
                            <path d="m873.89,579.81c13.61,0,24.65,12.54,24.65,28.02s-11.04,28.01-24.65,28.01-24.65-12.54-24.65-28.01,11.04-28.02,24.65-28.02Zm15.17,28.02c0-9.52-6.79-17.24-15.17-17.24s-15.17,7.71-15.17,17.24,6.79,17.24,15.17,17.24,15.17-7.72,15.17-17.24Z" style="fill: #8080ad;"/>
                            <path d="m873.89,649.16c13.61,0,24.65,12.54,24.65,28.01s-11.04,28.01-24.65,28.01-24.65-12.54-24.65-28.01,11.04-28.01,24.65-28.01Zm15.17,28.01c0-9.52-6.79-17.24-15.17-17.24s-15.17,7.72-15.17,17.24,6.79,17.24,15.17,17.24,15.17-7.72,15.17-17.24Z" style="fill: #8080ad;"/>
                            <path d="m873.89,590.59c8.38,0,15.17,7.71,15.17,17.24s-6.79,17.24-15.17,17.24-15.17-7.72-15.17-17.24,6.79-17.24,15.17-17.24Z" style="fill: #1b1344;"/>
                            <path d="m873.89,659.94c8.38,0,15.17,7.72,15.17,17.24s-6.79,17.24-15.17,17.24-15.17-7.72-15.17-17.24,6.79-17.24,15.17-17.24Z" style="fill: #1b1344;"/>
                        </g>
                        <ellipse id="pack-light1" class="pack-light" cx="883.73" cy="380.11" rx="20.09" ry="22.83" style="fill: #00ffc2;"/>
                        <path id="pack-light2" class="pack-light" d="m873.89,590.59c8.38,0,15.17,7.71,15.17,17.24s-6.79,17.24-15.17,17.24-15.17-7.72-15.17-17.24,6.79-17.24,15.17-17.24Z" style="fill: #eb3cf2;"/>
                        <path id="pack-light3" class="pack-light" d="m873.89,659.94c8.38,0,15.17,7.72,15.17,17.24s-6.79,17.24-15.17,17.24-15.17-7.72-15.17-17.24,6.79-17.24,15.17-17.24Z" style="fill: #eb3cf2;"/>
                        <path id="pack-light4" class="pack-light" d="m1111.69,639.89c7.14,0,12.77,7.18,12.58,16.04-.19,8.86-6.12,16.04-13.26,16.04s-12.77-7.18-12.58-16.04c.19-8.86,6.12-16.04,13.26-16.04Z" style="fill: #eb3cf2;"/>
                        <g>
                            <path d="m907.4,271.24c-43.13-2.75-77.61-4.99-87.46-5.63" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <path d="m792.71,766.42s8.12,7.01,30.31,4.74c45.29-4.64,319.89-35.99,319.89-35.99,0,0,29.53-.95,29.53-31.25v-382.61s3.94-34.43-32.48-35.99c-12.74-.54-40.44-2.14-74.53-4.19" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <path d="m819.94,265.62c-1.85-.12-2.84-.18-2.84-.18,0,0-14.99-1.73-24.59,2.88" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <path d="m864.76,727.16c36.25-5.16,257.32-28.95,257.32-28.95,0,0,23.75-.76,23.75-25.14v-307.77s3.17-27.7-26.13-28.95c-39.67-1.7-259.7-16-259.7-16,0,0-26.13-3.05-26.13,16.76v368.72s-2.37,26.07,30.88,21.33Z" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <line x1="928.82" y1="285.4" x2="958.86" y2="287.23" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <path d="m819.94,265.62c-.51-3.19-1.36-18.32,27.68-20.08,32.48-1.97,86.62-10.83,105.31-9.84,18.7.98,56.67,4.43,63.81,4.94,31,2.21,44.35,10.84,48.67,40.49" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <path d="m819.94,265.62c.06.38.12.59.12.59" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <path d="m1040.45,292.2l25.67,1.56c.12-4.5-.14-8.71-.71-12.64" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <path d="m958.86,287.23c.19,4.88,6.96,21.32,20.03,25.53,14.94,4.82,48.94,2.68,48.94,2.68,0,0,8.54-3.53,12.63-23.24" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <path d="m958.84,285.89s-.02.49.01,1.34" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <path d="m1040.45,292.2c.03-.14.06-.27.08-.4" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <line x1="907.4" y1="271.24" x2="928.82" y2="285.4" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <path d="m1000.45,442.49c-28.66,5.74-50.41,33.7-50.41,67.33s21.75,61.58,50.41,67.32" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <path d="m1000.45,577.14c3.54.71,7.19,1.08,10.92,1.08,33.87,0,61.33-30.63,61.33-68.41s-27.46-68.41-61.33-68.41c-3.73,0-7.37.37-10.92,1.08" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <path d="m1000.45,577.14c28.66-5.74,50.41-33.7,50.41-67.32s-21.75-61.58-50.41-67.33" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <path d="m1013.24,404.75c48.45,5.14,86.35,50.66,86.35,106.05s-41.29,104.87-92.77,106.49" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <path d="m1013.24,404.75c-3.01-.32-6.07-.49-9.15-.49-52.75,0-95.51,47.7-95.51,106.53s42.76,106.53,95.51,106.53c.92,0,1.83-.02,2.74-.04" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <path d="m1013.24,404.75c-7.36-2.01-15.04-3.07-22.95-3.07-53.48,0-96.83,48.62-96.83,108.59s43.35,108.59,96.83,108.59c5.64,0,11.16-.54,16.53-1.58" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <ellipse cx="883.73" cy="380.11" rx="20.09" ry="22.83" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <path d="m916.38,380.11c0-20.49-14.62-37.1-32.65-37.1s-32.65,16.61-32.65,37.1,14.62,37.1,32.65,37.1,32.65-16.61,32.65-37.1Z" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <path d="m889.06,607.82c0-9.52-6.79-17.24-15.17-17.24s-15.17,7.71-15.17,17.24,6.79,17.24,15.17,17.24,15.17-7.72,15.17-17.24Z" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <path d="m898.54,607.82c0-15.47-11.04-28.02-24.65-28.02s-24.65,12.54-24.65,28.02,11.04,28.01,24.65,28.01,24.65-12.54,24.65-28.01Z" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <path d="m858.72,677.18c0,9.52,6.79,17.24,15.17,17.24s15.17-7.72,15.17-17.24-6.79-17.24-15.17-17.24-15.17,7.72-15.17,17.24Z" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <ellipse cx="873.89" cy="677.18" rx="24.65" ry="28.01" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <path d="m1111.01,671.98c7.14,0,13.07-7.18,13.26-16.04.19-8.86-5.45-16.04-12.58-16.04s-13.07,7.18-13.26,16.04c-.19,8.86,5.45,16.04,12.58,16.04Z" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                            <path d="m1110.8,682.01c11.6,0,21.25-11.67,21.55-26.07s-8.85-26.07-20.45-26.07-21.24,11.67-21.55,26.07c-.3,14.4,8.85,26.07,20.45,26.07Z" style="fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                        </g>
                    </g>
                    <path d="m928.9,278.28c1.48-2.21-5.17-34.7,17.72-35.43,22.88-.74,97.44,8.12,97.44,8.12,0,0,19.19,12.55,16.98,36.17h-26.58s0,22.15-9.6,23.62-40.6-1.48-40.6-1.48c0,0-19.93-3.69-22.15-28.79l-33.22-2.21Z" style="opacity: .2;"/>
                    <path d="m900.36,550.68h15.26s13.29,55.12,82.19,65.95c51.67-3.44,71.36-28.05,72.84-29.53s18.7-25.1,21.16-33.96c0,0-20.18,78.74-91.04,79.73-70.87.98-95.97-49.71-100.4-82.19Z" style="opacity: .2;"/>
                    <path d="m949.57,518.2c-1.84.34-1.88-76.45,67.42-76.28,28.54,2.46,47.74,25.59,51.67,44.29l-21.65.49s-49.21,12.8-97.44,31.5Z" style="opacity: .2;"/>
                    <path d="m1013.03,303.44l-22.9-1.52c-3.36-.22-6.08-3-6.08-6.21v-22.51c0-3.21,2.72-5.62,6.08-5.4l22.9,2.17c3.36.22,6.08,3,6.08,6.21v21.86c0,3.21-2.72,5.62-6.08,5.4Z" style="fill: #51ff43; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2.83px;"/>
                    <path d="m856.25,741.63c-1.76,0-4.49-.14-7.75-.78-7.18-1.41-13.69-4.65-18.82-9.35-9.44-8.66-14.23-21.81-14.23-39.08v-348.43c-.11-2.64-.06-15.55,10.21-25.53,8.67-8.42,21.66-11.89,38.59-10.31,26.57,2.48,126.12,12.93,192,16.45,43.11,2.3,65.75,3.52,68.97,3.87.31.03-1.54,2.24-1.21,2.27,5.15.54,13.78,1.44,21.36,6.85,9.23,6.59,13.92,17.53,13.92,32.52v313.14c-.04.83-.47,2.18-5.09,10.18-4.37,7.57-13.33,17-31.18,18.87-27.29,2.87-262.27,29.02-264.67,29.25h-.06c-.27.03-.98.08-2.04.08Zm-23.55-393.23l.03.32-.02.18v343.51c0,12.13,2.85,20.96,8.49,26.23,6.42,6.01,14.96,5.75,15.64,5.72h-.1s.04,0,.07,0h.03s0,0,0,0c6.23-.58,239.56-22.34,266.53-25.18,8.28-.87,14.25-4.18,17.73-9.83,2.51-4.07,3.01-8.14,3.08-8.93v-312.48c0-20.14-10.52-21.23-19.8-22.2-.36-.04-.71-.07-1.04-.11-2.89-.32-33.07-1.93-68.02-3.79-69.96-3.74-165.76-8.85-192.68-11.37-11.41-1.07-20,.79-24.83,5.38-5.13,4.86-5.13,11.91-5.12,12.56Zm0-.05h0Z" style="opacity: .2;"/>
                    <path d="m1013.1,452.39l-54.18,82.65s6.89,23.88,26.17,30.76c2.75-1.84,56.02-86.78,56.02-86.78,0,0-12.86-22.04-28.01-26.63Z" style="fill: #fff; opacity: .3;"/>
                </g>
            </g>

            <g id="dust">
                <g class="speck-wrapper">
                    <circle id="speck10" class="speck" cx="703.01" cy="248.02" r="13.29" style="fill: #f8a4ff;"/>
                </g>

                <g class="speck-wrapper">
                    <circle id="speck9" class="speck" cx="632.15" cy="398.61" r="13.29" style="fill: #f8a4ff;"/>
                </g>

                <g class="speck-wrapper">
                    <circle id="speck8" class="speck" cx="728.11" cy="568.4" r="13.29" style="fill: #f8a4ff;"/>
                </g>

                <g class="speck-wrapper">
                    <circle id="speck7" class="speck" cx="819.65" cy="432.57" r="13.29" style="fill: #f8a4ff;"/>
                </g>

                <g class="speck-wrapper">
                    <circle id="speck6" class="speck" cx="773.88" cy="676.17" r="13.29" style="fill: #f8a4ff;"/>
                </g>

                <g class="speck-wrapper">
                    <circle id="speck5" class="speck" cx="656.79" cy="666.39" r="13.29" style="fill: #f8a4ff;"/>
                </g>

                <g class="speck-wrapper">
                    <circle id="speck4" class="speck" cx="1122.66" cy="222.2" r="13.29" style="fill: #f8a4ff;"/>
                </g>

                <g class="speck-wrapper">
                    <circle id="speck3" class="speck" cx="1035.21" cy="698.32" r="13.29" style="fill: #f8a4ff;"/>
                </g>

                <g class="speck-wrapper">
                    <circle id="speck2" class="speck" cx="1085.4" cy="370.56" r="13.29" style="fill: #f8a4ff;"/>
                </g>

                <g class="speck-wrapper">
                    <circle id="speck1" class="speck" cx="1169.96" cy="581.56" r="13.29" style="fill: #f8a4ff;"/>
                </g>
            </g>
        </g>



    </g>
</svg>
