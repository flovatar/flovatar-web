<x-guest-layout>
    <script>
        @if(isset($templates))
            window.templates = @json($templates);
        @endif
        window.watermarkSvg = '<g opacity=".5" fill="#fff"><path opacity=".5" d="M746.5 176C433.08 176 179 430.08 179 743.5c0 313.42 254.08 567.5 567.5 567.5 313.42 0 567.5-254.08 567.5-567.5 0-313.42-254.08-567.5-567.5-567.5zm390.37 957.87a550.234 550.234 0 0 1-362.682 160.01c-136.576 6.41-270.65-38.24-376.096-125.28a550.211 550.211 0 0 1-194.295-345.53 550.22 550.22 0 0 1 88.353-386.438 550.227 550.227 0 0 1 718.31-176.166c120.21 65.131 211.88 172.688 257.12 301.711a550.21 550.21 0 0 1-12.38 396.218 549.964 549.964 0 0 1-118.33 175.475z"/><path opacity=".5" d="M1119.7 743.5c0-206.104-167.094-373.193-373.2-373.193-206.104 0-373.193 167.089-373.193 373.193 0 206.106 167.089 373.2 373.193 373.2 206.106 0 373.2-167.094 373.2-373.2zm-233.947 329.66a358.993 358.993 0 0 1-488.79-415.948 358.992 358.992 0 0 1 400.252-269.837 358.995 358.995 0 0 1 225.455 126.952 359.03 359.03 0 0 1 81.55 245.559 359.021 359.021 0 0 1-104.735 236.599 355.458 355.458 0 0 1-113.732 76.675zM270.71 555.069l62.919 29.299a27.543 27.543 0 0 0 14.265 2.53 28.435 28.435 0 0 0 13.416-4.748 25.556 25.556 0 0 0 9.161-10.796 26.226 26.226 0 0 0 2.374-14.111 28.049 28.049 0 0 0-16.392-22.453l-63.135-28.776a.492.492 0 0 0-.74.277l-6.508 14.111a.495.495 0 0 0 .278.74l63.011 29.053a11.335 11.335 0 0 1 5.998 6.046 9.686 9.686 0 0 1-.138 8.111 9.563 9.563 0 0 1-6.014 5.259 11.426 11.426 0 0 1-8.497-.632l-63.012-29.038a.467.467 0 0 0-.433-.047.472.472 0 0 0-.291.324l-6.508 14.111a.49.49 0 0 0 .058.62.5.5 0 0 0 .188.12zM308.537 480.152l78.386 52.802c.54.355 1.003.232 1.419-.37l7.71-11.504c.479-.694.402-1.249-.215-1.665l-41.946-27.157 55.763 8.697 6.847-10.116c.247-.386.154-.74-.308-1.049l-78.51-52.879c-.617-.417-1.079-.385-1.388 0l-7.71 11.396a1.052 1.052 0 0 0-.293.883 1.053 1.053 0 0 0 .524.767l44.721 29.424-58.431-11.181-7.001 10.348a.992.992 0 0 0-.222.92.984.984 0 0 0 .654.684zM425.492 477.747c.339.324.632.354.895.077l10.887-11.273c.262-.277.216-.57-.108-.895l-50.89-49.255 25.198 4.272-5.058-25.137 51.029 49.055c.329.329.622.36.879.093l10.887-11.289c.262-.262.232-.555-.108-.879l-68.794-66.033a.491.491 0 0 0-.786 0l-11.859 12.337a1.238 1.238 0 0 0-.278 1.079l4.426 21.775-21.898-3.608a1.256 1.256 0 0 0-1.08.323l-11.75 12.183a.495.495 0 0 0 0 .787l68.408 66.388zM481.717 423.124a.64.64 0 0 0 .468.294.619.619 0 0 0 .28-.034.615.615 0 0 0 .239-.152l12.337-9.73c.293-.232.293-.525 0-.895l-59.325-74.685a.482.482 0 0 0-.363-.158.493.493 0 0 0-.362.158l-12.183 9.654a.462.462 0 0 0-.165.152.463.463 0 0 0-.052.433.471.471 0 0 0 .125.186l59.001 74.777zM507.78 401.982c.34.556.833.633 1.542.247l11.844-7.217c.709-.447.879-.972.493-1.542l-26.832-42.162 47.08 31.12 10.286-6.415c.401-.247.463-.602.17-1.08l-49.348-80.745c-.385-.632-.817-.817-1.295-.524L490 300.819c-.725.432-.88.972-.494 1.542l28.468 45.4-48.454-34.512-10.671 6.507a1.008 1.008 0 0 0-.368.271.993.993 0 0 0-.002 1.272l49.301 80.683zM530.865 296.61a.607.607 0 0 0 .36.42.587.587 0 0 0 .281.042.613.613 0 0 0 .269-.092l16.655-6.924 30.394 73.034c.172.432.431.571.771.432l14.483-6.029c.336-.139.432-.417.244-.848l-30.517-72.989 16.793-6.986c.34-.139.416-.432.248-.848l-5.969-14.357c-.138-.34-.431-.417-.863-.247l-48.685 20.264a.494.494 0 0 0-.308.724l5.844 14.404zM622.205 346.944l43.458-10.995a.493.493 0 0 0 .212-.07.456.456 0 0 0 .156-.159.454.454 0 0 0 .068-.214.46.46 0 0 0-.036-.22l-3.869-15.345c-.124-.447-.356-.632-.711-.539l-27.76 7.016-5.966-23.548 27.759-7.016a.493.493 0 0 0 .212-.07.494.494 0 0 0 .224-.373.467.467 0 0 0-.036-.221l-3.981-15.174a.456.456 0 0 0-.076-.213.49.49 0 0 0-.375-.224.486.486 0 0 0-.224.036l-27.576 6.97-5.287-20.988 27.571-6.986a.496.496 0 0 0 .372-.229.503.503 0 0 0 .068-.213.522.522 0 0 0-.036-.221l-3.813-15.082c-.107-.447-.335-.617-.691-.524l-43.182 10.903a.497.497 0 0 0-.4.678l23.224 92.528c.08.4.324.585.695.493zM682.317 332.757l24.674-2.375a29.118 29.118 0 0 0 23.719-16.701 28.31 28.31 0 0 0 2.558-14.943l-3.593-39.664a26.665 26.665 0 0 0-4.98-13.555 28.36 28.36 0 0 0-10.934-9.114 25.8 25.8 0 0 0-14.22-2.344l-25.829 2.468a.5.5 0 0 0-.395.179.535.535 0 0 0-.104.199.467.467 0 0 0-.008.223l8.312 94.994a.615.615 0 0 0 .048.294.624.624 0 0 0 .752.339zm18.783-82.103a10.086 10.086 0 0 1 7.941 2.452 10.572 10.572 0 0 1 3.949 7.387l3.592 39.802c.164 1.592.008 3.2-.463 4.729a11.925 11.925 0 0 1-2.278 4.169 11.798 11.798 0 0 1-3.709 3.053 11.756 11.756 0 0 1-4.62 1.312l-8.56.786-5.519-62.641 9.667-1.049zM777.033 329.148l15.423 1.974a.601.601 0 0 0 .771-.602l5.179-40.403 28.224 3.624c.463 0 .707-.093.755-.463l1.866-15.498c0-.447-.092-.709-.463-.756l-28.219-3.624 2.729-21.327 28.219 3.624a.516.516 0 0 0 .436-.093.451.451 0 0 0 .144-.178.478.478 0 0 0 .052-.223l1.99-15.544c0-.37-.156-.586-.616-.648l-44.045-5.644a.487.487 0 0 0-.579.255.484.484 0 0 0-.052.223l-12.337 94.532a.633.633 0 0 0-.004.26.594.594 0 0 0 .096.241.695.695 0 0 0 .187.18c.076.047.16.077.244.09zM839.442 338.554l43.182 12.245a.634.634 0 0 0 .552-.023.596.596 0 0 0 .208-.191.648.648 0 0 0 .103-.264l4.273-15.098a.647.647 0 0 0 .052-.276.587.587 0 0 0-.268-.475.595.595 0 0 0-.264-.097l-27.496-7.819 21.665-75.795a.593.593 0 0 0-.212-.751.61.61 0 0 0-.263-.097l-14.819-4.21a.605.605 0 0 0-.852.478l-26.212 91.556c-.064.432-.064.709.351.817zM905.244 357.245a26.471 26.471 0 0 0 10.895 9.099 26.439 26.439 0 0 0 14.027 2.159 28.676 28.676 0 0 0 13.141-5.182 27.076 27.076 0 0 0 9.096-11.196l17.996-40.095a26.828 26.828 0 0 0 2.362-14.172 28.614 28.614 0 0 0-4.796-13.139 25.535 25.535 0 0 0-10.795-9.006 26.037 26.037 0 0 0-13.879-2.19 28.025 28.025 0 0 0-13.277 4.981 26.815 26.815 0 0 0-9.068 11.119l-18.243 40.095a26.762 26.762 0 0 0-2.33 14.249 29.323 29.323 0 0 0 4.871 13.278zm12.893-20.603 18.12-40.095a10.808 10.808 0 0 1 5.999-5.891 9.86 9.86 0 0 1 7.989.185 9.527 9.527 0 0 1 3.353 2.373 9.53 9.53 0 0 1 2.046 3.565 11.155 11.155 0 0 1-.54 8.281l-17.996 40.095a11.203 11.203 0 0 1-6.015 5.737 9.723 9.723 0 0 1-11.374-2.326 9.78 9.78 0 0 1-2.03-3.581 10.984 10.984 0 0 1 .448-8.343zM964.216 390.216l14.096 9.129a.692.692 0 0 0 .955-.2l67.683-69.766c.28-.262.23-.524-.17-.771l-13.04-8.451a.67.67 0 0 0-.46-.127.638.638 0 0 0-.42.22l-43.878 47.312 24.998-59.541a.61.61 0 0 0 .02-.478.67.67 0 0 0-.3-.371l-13.15-8.527c-.4-.247-.649-.185-.773.169l-35.761 90.446a.683.683 0 0 0-.095.519.69.69 0 0 0 .295.437zM996.324 411.127l11.656 10.07c.28.247.6.232.99 0l7.71-6.662 14.28 12.337-5.34 8.605a.675.675 0 0 0-.19.486c0 .181.06.356.19.486l11.64 10.085c.36.293.66.247.88-.169l49.88-82.92c.09-.14.12-.307.09-.47a.676.676 0 0 0-.25-.409l-15.22-13.154a.656.656 0 0 0-.89 0l-75.426 60.821c-.248.354-.264.647 0 .894zm62.466-31.49 3.78-3.531-2.73 4.441-21.2 33.217-10.09-8.713 30.24-25.414zM1110.32 408.259l-59.13 52.57c-.34.309-.38.602-.14.879l10.41 11.72c.25.278.54.263.9 0l59.01-52.678 12.09 13.586c.25.277.54.277.88 0l11.63-10.332c.26-.247.26-.54 0-.88l-35.05-39.416a.5.5 0 0 0-.17-.144.488.488 0 0 0-.44 0 .534.534 0 0 0-.18.144l-11.7 10.224a.556.556 0 0 0-.18.218.538.538 0 0 0-.07.276c0 .096.02.19.07.275.04.086.1.161.18.218l11.89 13.34zM1074.38 487.786l8.64 12.768c.2.288.52.35.97.185l9.26-4.426 10.58 15.638-7.53 6.939c-.34.34-.4.663-.19.972l8.64 12.753c.26.37.55.401.89 0l69.68-67.144a.68.68 0 0 0 .21-.424.678.678 0 0 0-.14-.455l-11.29-16.67a.662.662 0 0 0-.37-.289.676.676 0 0 0-.47.026l-88.6 39.217c-.4.323-.49.632-.28.91zm68.44-14.157 4.63-2.406-3.78 3.563-29.11 26.601-7.48-11.042 35.74-16.716zM1111.32 543.981l6.62 14.218c.15.34.43.417.86.216l37.01-17.086 3.85 8.311c.72 1.46 1.12 3.053 1.17 4.677.06 1.624-.23 3.241-.84 4.746a11.972 11.972 0 0 1-2.5 4.071 12.101 12.101 0 0 1-3.87 2.791l-26.05 12.105c-.26.124-.31.355-.15.695l6.61 14.22c.04.088.08.171.15.239.07.068.15.12.24.148a.599.599 0 0 0 .54-.064l25.74-12.121a28.316 28.316 0 0 0 14.59-15.052c1.73.756 3.54 1.3 5.41 1.619 2.58.473 5.21.587 7.82.339a28.103 28.103 0 0 0 11.3-3.719 27.98 27.98 0 0 0 8.74-8.062 28 28 0 0 0 4.63-13.278c.45-4.831-.4-9.694-2.48-14.079l-11.83-25.415a.506.506 0 0 0-.11-.199.478.478 0 0 0-.63-.063l-86.57 39.956c-.28.186-.4.448-.25.787zm79.39-18.86 5.04 10.795c.61 1.294.96 2.699 1.02 4.129.05 1.431-.17 2.858-.68 4.198a10.763 10.763 0 0 1-2.16 3.688 10.625 10.625 0 0 1-11.83 2.743 10.836 10.836 0 0 1-6.17-5.567l-5.02-10.795 19.8-9.191zM386.692 953.967a.495.495 0 0 0 .19-.116.474.474 0 0 0 .041-.624l-7.001-13.879a.46.46 0 0 0-.115-.192.482.482 0 0 0-.417-.14.448.448 0 0 0-.208.088l-61.963 31.256a11.325 11.325 0 0 1-8.466.927 9.652 9.652 0 0 1-3.65-1.898 9.6 9.6 0 0 1-2.518-3.253 9.457 9.457 0 0 1-.37-7.973 11.32 11.32 0 0 1 5.767-6.262l61.963-31.257a.56.56 0 0 0 .188-.124.51.51 0 0 0 .058-.631l-7.001-13.88a.489.489 0 0 0-.125-.188.474.474 0 0 0-.193-.116.542.542 0 0 0-.226-.024.575.575 0 0 0-.212.08l-62.07 31.013a27.578 27.578 0 0 0-10.795 9.607 28.342 28.342 0 0 0-4.626 13.449 25.58 25.58 0 0 0 2.714 13.879 26.078 26.078 0 0 0 9.576 10.639 28.033 28.033 0 0 0 13.728 4.248c4.868.2 9.703-.871 14.031-3.105l61.7-31.524zM423.658 1006.06l-8.019-11.134c-.494-.679-1.033-.803-1.635-.371l-43.056 31.895 30.642-51.014-7.294-10.131a.98.98 0 0 0-1.156-.472.969.969 0 0 0-.386.224l-76.598 55.373c-.524.37-.57.87-.138 1.47l8.111 11.26c.494.68 1.033.8 1.635.36l39.972-30.01-27.404 49.35 7.14 9.91c.278.38.632.4 1.079.07l76.721-55.52c.571-.36.71-.8.386-1.26zM476.443 1060.58l-12.151-11.98a1.13 1.13 0 0 0-.505-.28 1.121 1.121 0 0 0-.575-.02l-21.867 4.18 3.871-21.91c.042-.19.037-.39-.014-.58a1.167 1.167 0 0 0-.279-.5l-12.06-11.89a.478.478 0 0 0-.173-.15.557.557 0 0 0-.22-.05.557.557 0 0 0-.22.05.478.478 0 0 0-.173.15l-67.144 67.7c-.324.33-.355.63-.093.89l11.165 11.01c.262.25.556.22.879-.11l49.811-50.3-4.534 25.18 25.183-4.78-49.595 50.49c-.339.34-.37.63-.092.89l11.165 11.01c.262.25.555.22.879-.1l66.742-68.09c.067-.04.122-.1.16-.17a.57.57 0 0 0 .057-.24c0-.08-.02-.16-.057-.23a.444.444 0 0 0-.16-.17z"/><path opacity=".5" d="m501.412 1078.99-12.337-9.39a.515.515 0 0 0-.193-.13.583.583 0 0 0-.228-.02.54.54 0 0 0-.213.08.674.674 0 0 0-.153.17l-57.752 75.86a.622.622 0 0 0-.171.52c.013.1.048.19.102.27.054.07.125.13.208.18l12.491 9.49c.293.23.571.15.864-.22l57.536-76.02a.511.511 0 0 0 .107-.21.52.52 0 0 0 .008-.23.496.496 0 0 0-.094-.2.399.399 0 0 0-.175-.15zM557.929 1114.57l-11.905-6.96c-.725-.41-1.265-.31-1.65.32l-26.509 46.56 7.448-59.03-10.795-6.32a.93.93 0 0 0-.412-.21.908.908 0 0 0-.463.01.836.836 0 0 0-.407.22.903.903 0 0 0-.259.38l-47.729 81.58c-.324.55-.17 1.03.478 1.4l11.967 7.02c.709.43 1.264.32 1.634-.33l24.458-43.58-5.15 56.21 10.533 6.17c.4.23.74.1 1.017-.37l47.806-81.73c.478-.61.37-1.07-.062-1.34zM610.344 1153.95l5.783-14.42c.14-.35.004-.6-.432-.79l-48.929-19.65a.554.554 0 0 0-.217-.07.433.433 0 0 0-.223.04.47.47 0 0 0-.285.33l-5.922 14.37a.642.642 0 0 0-.035.56c.038.08.095.16.168.22.073.07.16.11.253.14l16.732 6.69-29.485 73.4c-.186.43-.093.71.246.85l14.558 5.86c.339.12.601 0 .771-.45l29.301-73.45 16.869 6.77c.403.17.663.03.847-.4zM668.517 1152.16l-43.29-10.58c-.068-.03-.144-.04-.22-.03a.395.395 0 0 0-.212.06c-.068.04-.12.1-.16.16a.54.54 0 0 0-.072.22l-22.76 92.52c-.124.47 0 .72.368.82l43.55 10.64c.072.03.148.04.223.03a.521.521 0 0 0 .212-.07.496.496 0 0 0 .228-.38l3.765-15.42c.108-.45 0-.72-.372-.81l-27.755-6.79 5.75-23.6 27.756 6.79c.072.03.148.04.224.04a.54.54 0 0 0 .216-.07.434.434 0 0 0 .164-.16.463.463 0 0 0 .075-.21l3.593-15.27c.028-.07.036-.15.028-.22a.54.54 0 0 0-.072-.22.531.531 0 0 0-.371-.24l-27.636-6.75 5.135-21.02 27.636 6.74c.072.03.148.04.224.03.076 0 .152-.02.216-.06.064-.04.12-.1.164-.16.04-.06.068-.14.075-.21l3.685-15.1c.108-.31-.016-.58-.367-.68zM722.969 1161.92a26.013 26.013 0 0 0-13.588-4.81l-25.877-2.11a.568.568 0 0 0-.224.01.5.5 0 0 0-.376.5l-8.56 94.96a.66.66 0 0 0 0 .28c.024.1.068.18.127.26.06.07.14.12.228.16.088.03.184.05.276.04l24.754 2.02c5.072.43 10.167-.47 14.779-2.62a29.164 29.164 0 0 0 11.514-9.64 28.373 28.373 0 0 0 5.148-14.25l3.457-39.67c.475-4.88-.396-9.8-2.514-14.22a28.51 28.51 0 0 0-9.144-10.91zm-5.06 23.52-3.469 39.82a12.01 12.01 0 0 1-1.295 4.57 11.765 11.765 0 0 1-2.977 3.69 11.537 11.537 0 0 1-4.18 2.37c-1.543.49-3.169.66-4.78.49l-8.573-.76 5.612-62.62 9.699.75c1.431.07 2.833.44 4.108 1.09 1.275.66 2.398 1.57 3.293 2.69a10.43 10.43 0 0 1 2.095 3.69c.439 1.36.599 2.8.467 4.22zM824.531 1191.04l-28.235 3.5-2.638-21.34 28.236-3.5c.075 0 .151-.02.219-.06a.463.463 0 0 0 .172-.14.439.439 0 0 0 .092-.2.455.455 0 0 0-.004-.22l-1.85-15.58c0-.35-.308-.52-.756-.46l-44.073 5.47c-.08 0-.156.02-.224.05a.485.485 0 0 0-.275.35.455.455 0 0 0 .004.22l11.398 94.66c-.004.09.016.19.056.28.04.08.099.16.175.22.072.06.164.1.256.11.096.02.192.01.284-.01l15.418-1.91c.096 0 .192-.02.28-.06.084-.04.16-.1.22-.18.06-.07.1-.16.116-.25.02-.1.016-.19-.012-.29l-4.996-40.35 28.295-3.5c.448 0 .664-.28.62-.63l-2.162-15.57c0-.44-.244-.66-.616-.61zM901.312 1215.06a.64.64 0 0 0-.1-.26.586.586 0 0 0-.208-.18.615.615 0 0 0-.264-.08.528.528 0 0 0-.276.05l-27.511 7.57-20.818-76.06a.632.632 0 0 0-.096-.26.527.527 0 0 0-.208-.19.517.517 0 0 0-.268-.08.833.833 0 0 0-.275.05l-14.851 4.07a.62.62 0 0 0-.264.11.552.552 0 0 0-.196.21.665.665 0 0 0-.08.27c-.008.09.008.19.044.28l25.09 91.85c.124.44.356.61.727.51l43.178-11.88a.61.61 0 0 0 .264-.09c.08-.06.144-.13.192-.21a.627.627 0 0 0 .024-.55l-4.104-15.13zM947.191 1137.74a26.95 26.95 0 0 0-8.912-11.24 28.354 28.354 0 0 0-12.892-5.18 25.58 25.58 0 0 0-13.972 1.99 26.035 26.035 0 0 0-10.967 8.88 28.097 28.097 0 0 0-5.103 13.12c-.567 4.84.192 9.75 2.19 14.19l17.457 40.39a26.809 26.809 0 0 0 9.004 11.29 29.635 29.635 0 0 0 13.156 5.32c4.776.64 9.636-.04 14.056-1.97a26.41 26.41 0 0 0 11.002-8.97 28.58 28.58 0 0 0 4.984-13.23c.563-4.86-.2-9.78-2.206-14.24l-17.797-40.35zm2.546 55.3a9.625 9.625 0 0 1-5.323 6.03 9.628 9.628 0 0 1-4.025.87 9.654 9.654 0 0 1-4.024-.87 11.03 11.03 0 0 1-5.923-5.97l-17.592-40.32a10.774 10.774 0 0 1-.34-8.41 10.01 10.01 0 0 1 2.134-3.48c.947-1 2.086-1.79 3.353-2.33a9.584 9.584 0 0 1 8.021 0 11.152 11.152 0 0 1 5.783 5.97l17.72 40.26a11.133 11.133 0 0 1 .216 8.25zM989.382 1082.32c-.12-.35-.384-.42-.771-.19l-13.201 8.24a.66.66 0 0 0-.291.83l24.194 59.82-43.27-47.93a.67.67 0 0 0-.879-.11l-13.308 8.3c-.4.25-.464.51-.184.77l66.678 70.78a.703.703 0 0 0 .96.23l14.26-8.88c.15-.1.26-.26.3-.43.04-.18.01-.37-.08-.52l-34.408-90.91zM1019.1 1057.82a.656.656 0 0 0-.88 0l-15.51 12.82c-.13.11-.22.25-.26.41-.03.16-.01.33.07.47l47.53 84.44c.31.35.59.41.88.16l11.86-9.8a.73.73 0 0 0 .11-.99l-5.29-8.78 14.54-12.02 7.71 6.83c.37.3.69.33.97.09l11.88-9.82c.35-.29.35-.59 0-.9l-73.61-62.91zm31.2 65.53-20.05-34.01-2.84-4.32 3.92 3.42 29.3 26.43-10.33 8.48zM1067.26 1033.23l12.43-13.27c.26-.27.22-.56-.12-.88l-11.34-10.63c-.28-.24-.57-.22-.88.12l-36.07 38.56a.7.7 0 0 0-.13.17c-.04.07-.05.15-.05.22 0 .08.01.15.05.22.03.07.08.13.13.18l11.24 10.79c.06.08.13.14.22.18.09.04.18.07.28.07.09 0 .19-.03.27-.07.09-.04.16-.1.22-.18l12.34-13.16 57.73 54.09c.34.32.64.34.88 0l10.8-11.44c.26-.27.21-.56-.12-.88l-57.88-54.09zM1176.75 1021.08l-86.97-42.439a.665.665 0 0 0-.48-.04.64.64 0 0 0-.37.288l-11.79 16.317a.7.7 0 0 0-.17.44c0 .16.07.32.17.439l67.31 69.715c.36.26.67.25.89 0l9-12.49c.22-.29.17-.61-.15-.97l-7.31-7.17 11.04-15.3 9.12 4.63c.43.2.75.15.97-.16l9-12.49c.28-.32.2-.6-.26-.77zm-40.33 13.17-27.95-27.89-3.84-3.46 4.62 2.33 34.95 18.21-7.78 10.81zM1214.9 950.605l-25.41-12.796a28.332 28.332 0 0 0-20.85-2.19 23.63 23.63 0 0 0-2.07-5.26 29.228 29.228 0 0 0-4.54-6.37 26.771 26.771 0 0 0-7.55-5.535 26.237 26.237 0 0 0-14.12-2.978c-4.76.34-9.35 1.887-13.34 4.488a27.067 27.067 0 0 0-9.55 10.643l-12.71 24.998a.497.497 0 0 0-.06.432c.02.072.06.14.12.192.05.051.12.091.19.115l84.82 43.47c.41.216.71.156.88-.168l7.11-13.987c.17-.324 0-.6-.37-.8l-36.28-18.615 4.15-8.157a11.73 11.73 0 0 1 2.97-3.801 11.59 11.59 0 0 1 4.25-2.274 12 12 0 0 1 4.74-.547c1.61.136 3.17.587 4.59 1.331l25.62 13.032c.24.124.44 0 .61-.308l7.11-13.987a.625.625 0 0 0 .11-.26.684.684 0 0 0-.03-.284.568.568 0 0 0-.15-.236.522.522 0 0 0-.24-.148zm-63.23-2.541-5.41 10.638-19.41-9.883 5.41-10.643a10.636 10.636 0 0 1 6.34-5.411 10.61 10.61 0 0 1 4.24-.559c1.43.1 2.84.491 4.12 1.151 1.27.659 2.41 1.57 3.33 2.681.91 1.107 1.6 2.394 2 3.773.46 1.347.64 2.766.55 4.18-.1 1.419-.47 2.802-1.1 4.073h-.07zM282.321 768.174c13.628 0 24.674-11.046 24.674-24.674s-11.046-24.674-24.674-24.674c-13.626 0-24.674 11.046-24.674 24.674s11.048 24.674 24.674 24.674zM1204.51 768.174c13.63 0 24.67-11.046 24.67-24.674s-11.04-24.674-24.67-24.674c-13.63 0-24.68 11.046-24.68 24.674s11.05 24.674 24.68 24.674zM952.834 529.146H592.596a15.603 15.603 0 0 0-11.019 4.571 15.598 15.598 0 0 0-4.572 11.02V961.82a17.652 17.652 0 0 0 5.172 12.453 17.65 17.65 0 0 0 12.453 5.171h179.506a17.64 17.64 0 0 0 17.624-17.624V835.687h106.282a14.965 14.965 0 0 0 10.567-4.38 14.966 14.966 0 0 0 4.392-10.563V718.966a14.99 14.99 0 0 0-4.384-10.575 14.983 14.983 0 0 0-10.575-4.384H791.76v-43.242h161.074a15.608 15.608 0 0 0 11.014-4.564 15.61 15.61 0 0 0 4.576-11.01V544.737a15.607 15.607 0 0 0-4.568-11.02 15.616 15.616 0 0 0-11.022-4.571zM594.322 961.82V546.464h39.833v415.663H594.63a.34.34 0 0 1-.208-.1.338.338 0 0 1-.1-.207zm356.786-318.372H774.443v77.939h121.241v96.983H774.443v143.418a.36.36 0 0 1-.1.211.344.344 0 0 1-.207.096H651.799a.33.33 0 0 1-.219-.092.324.324 0 0 1-.104-.215V546.464h299.632v96.984z"/></g>';
        window.childUnsafeIds = [15, 443, 444];
    </script>
    <div class="flovatar-builder"
         x-data="builderData(window.templates)"
         x-init="initBuilder($dispatch);"
         @user-loaded="getComponents(window.user.addr);"
         @availability-checked.window="handleCheckResults($event.detail)"
         @flovatar-created.window="handleFlovatarCreated()"
         @fcomponents-loaded.window="loadComponents($event.detail.components)"
         @builder-checkout-completed.window="processBuilderCheckoutCompleted()"
         @hashchange.window="loadHashParams(); loadCombination();"
    >
        <div class="flovatar-builder_bg"></div>
        <div class="flovatar-builder_frame">
            <div class="flovatar-builder_content">
                <div class="core-parts">
                    <div class="widget-frame">
                        <span class="triangle-icon scrl-up"></span>
                        <div class="widget-content scrollable-frame">
                            <div class="scroll-y">
                                <div class="scroll-content">
                                    <ul class="core-parts-list">
                                        <template x-for="category in basicCategories" :key="category">
                                            <li>
                                                <button class="btn part-btn" @click.prevent.stop="selectActiveCategory(category)" x-bind:class="getCategoryClass(category)">
                                                    <span class="inner">
                                                        <img x-bind:src="'/images/part-'+category+'.svg'" alt="">
                                                        <span class="txt" x-text="category"></span>
                                                    </span>
                                                </button>
                                            </li>
                                        </template>
                                    </ul>
                                    <strong class="title">Flobits</strong>
                                    <ul class="core-parts-list">
                                        <template x-for="category in optionalCategories" :key="category">
                                            <li>
                                                <button class="btn part-btn" @click.prevent.stop="selectActiveCategory(category)" x-bind:class="getCategoryClass(category)">
                                                    <span class="inner">
                                                        <img x-bind:src="'/images/part-'+category+'.svg'" alt="">
                                                        <span class="txt" x-text="category"></span>
                                                    </span>
                                                </button>
                                            </li>
                                        </template>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <span class="triangle-icon scrl-down"></span>
                    </div>
                    <span class="connector"></span>
                </div>
                <div class="flovatar-info">
                    <div class="widget-frame">
                        <div class="widget-content scroll-y">
                            <div class="flovatar-info-row" x-bind:class="infoBoxClass()">
                                <strong class="title" x-text="activeCategory"></strong>
                                <div class="flovatar">
                                    <button class="icon-cross-circle" x-show="showUnavailable()"></button>
                                    <div class="inner-wrap" x-bind:class="activeCategory == 'body' ? '' : 'builder-component-thumb'">
                                        <img x-bind:src="getTempUrl(activeCategory)" x-show="getTempUrl(activeCategory)" />
                                    </div>
                                </div>
                                <div class="desc">
                                    <dl>
                                        <dt>Name</dt>
                                        <dd x-text="getTempName(activeCategory)"></dd>
                                        <dt>Rarity</dt>
                                        <dd x-html="getTempRarity(activeCategory)"></dd>
                                        <div x-show="!isCategoryFlobit(activeCategory)">
                                            <dt>Used by</dt>
                                            <dd x-html="getTempBurned(activeCategory)"></dd>
                                        </div>
                                    </dl>
                                    <a x-show="isCategoryFlobit(activeCategory) && !componentIds[activeCategory] && templateIds[activeCategory]" x-bind:href="'/marketplace#templatecat=flobits&templateid='+templateIds[activeCategory]" class="btn btn-sm btn-red btn-search-marketplace"><div class="inner">Search Marketplace</div></a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <span class="connector"></span>
                </div>
                <div class="flovatar-parts">
                    <div class="widget-frame">
                        <div class="parts-filter">
                            <div class="btn-wrap" x-show="loggedIn()">
                                <button class="btn btn-sm btn-gray" x-bind:class="showOnlyAvailable ? '' : 'active'" @click.prevent.stop="showOnlyAvailable = false"><span class="inner" x-text="isCategoryFlobit(activeCategory) ? 'All Flobits' : 'All Traits'"></span></button>
                            </div>
                            <div class="btn-wrap" x-show="loggedIn()">
                                <button class="btn btn-sm btn-gray" x-bind:class="showOnlyAvailable ? 'active' : ''" @click.prevent.stop="showOnlyAvailable = true"><span class="inner">Available</span></button>
                            </div>
                            <div class="select-wrap hidden">
                                <select x-model="subgroupFilter">
                                    <option value="all">Everything</option>
                                    <option value="default">Genesis Drop</option>
                                    <option value="xmas">Holiday Airdrop</option>
                                    <option value="background">Background Airdrop</option>
                                </select>
                            </div>
                            <div class="btn-wrap btn-child"><button class="btn btn-sm btn-gray btn-icon"  x-bind:class="childModeEnabled ? 'active' : ''" @click.prevent.stop="childModeEnabled = !childModeEnabled"><span class="inner"><i class="icon-toddler"></i></span></button></div>
                            <div x-show="false" class="btn-wrap btn-search visible-md"><button class="btn btn-sm btn-red btn-icon"><span class="inner"><i class="icon-search"></i></span></button></div>
                        </div>
                        <div class="widget-content scrollable-frame">
                            <div class="scroll-y">
                                <div class="scroll-content">
                                    <ul class="parts-list">
                                        <template x-if="isCategoryOptional(activeCategory) || activeCategory == 'facialHair'">
                                            <li>
                                                <button class="part-card" @click.prevent.stop="selectComponent(null)" x-bind:class="(templateIds[activeCategory] == null) ? 'active' : ''">
                                                    <img src="/images/body-outline.svg" alt="">
                                                </button>
                                            </li>
                                        </template>
                                        <template x-for="template in filterTemp(activeCategory)" :key="template.id">
                                            <li x-bind:class="getComponentParentClass(template)">
                                                <button class="part-card" @click.prevent.stop="selectComponent(template.flow_id)" x-bind:class="getComponentClass(template)">
                                                    <div class="builder-component-thumb" x-bind:class="(mode3dEnabled) ? 'mode3d' : ''">
                                                        <img x-bind:src="getTemplateUrl(template.flow_id)" alt="">
                                                    </div>
                                                </button>
                                            </li>
                                        </template>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <span class="connector"></span>
                </div>
                <div class="flovatar-machine">
                    <div class="widget-frame machine-frame no-bg">
                        <div class="widget-content machine-box no-bg">
                            <div class="machine">
                                <div class="flovatar-img">
                                    <div class="builder-svg" x-html="getFullSvg()" x-show="!mode3dEnabled" id="flovatar-builder-box"></div>
                                    <div class="builder-svg" x-show="mode3dEnabled">
                                        <iframe
                                            id="visualiser"
                                            scrolling="no"
                                            style="width: 100%; height: 100%; aspect-ratio: 1; overflow: hidden; border: 0"
                                            src="https://webgl.flovatar.com/"
                                        ></iframe>
                                    </div>
                                    <img class="builder-svg watermark" src="/images/watermark.svg" alt="watermark"/>
                                </div>
                            </div>
                        </div>
                    </div>
                    <ul class="btns-list">
                        <li><button @click.prevent.stop="randomizeCombination()"  class="btn btn-lilac btn-tall"><span class="inner">Randomize</span></button></li>
                        <li>
                            <button @click.prevent.stop="checkAvailabilityClick()" x-show="!(showMintBtn && hasAllComponents())" class="btn btn-orange btn-tall"><span class="inner">Check Availability</span></button>
                            <button @click.prevent.stop="createFlovatarClick()" x-show="showMintBtn && hasAllComponents()" class="btn btn-green btn-tall"><span class="inner">Create Flovatar</span></button>
                        </li>
                        <li><button @click.prevent.stop="savePng()" class="btn btn-lilac btn-icon"><span class="inner"><i class="icon-camera"></i></span></button></li>
                        <li><button x-bind:class="mode3dEnabled ? 'active' : ''" @click.prevent.stop="mode3dEnabled = !mode3dEnabled" class="btn btn-lilac btn-pressable btn-icon"><span class="inner">3D</span></button></li>
                    </ul>
                    <div class="rarity-score alt">
                        <div class="widget-frame">
                            <div class="widget-content">
                                <div class="inner-circle">
                                    <img class="img-t" src="/images/text-rarity-score.svg" alt="">
                                    <strong class="score" x-bind:class="builderScoreClass()" x-text="builderScore()"></strong>
                                    <img class="img-b" x-bind:src="'/images/text-'+builderScoreClass()+'.svg'" alt="">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="booster-panel">
                        <div class="widget-frame">
                            <div class="widget-content">
                                <ul class="booster-list">
                                    <li class="has-border">
                                        <a href="/marketplace#templatecat=spark&sortby=pricelow">
                                            <img src="/images/spark.svg" alt="">
                                            <span class="data" x-bind:class="sparkCountColor()" x-text="getSparkCount()"></span>
                                            <div class="info-tooltip">
                                                <strong>Spark</strong>
                                                <p>The essential ingredient that allows you to mint your Flovatar with any common trait.</p>
                                            </div>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="/marketplace#templatecat=boost&rarity=rare&sortby=pricelow">
                                            <img src="/images/booster-rare.svg" alt="">
                                            <span class="data" x-bind:class="rarityCountColor('rare')" x-text="rarityFullCount('rare')"></span>
                                            <div class="info-tooltip">
                                                <strong>Rare Booster</strong>
                                                <p>Unlocks all Rare traits to be used by your Flovatar.</p>
                                            </div>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="/marketplace#templatecat=boost&rarity=epic&sortby=pricelow">
                                            <img src="/images/booster-epic.svg" alt="">
                                            <span class="data" x-bind:class="rarityCountColor('epic')" x-text="rarityFullCount('epic')"></span>
                                            <div class="info-tooltip">
                                                <strong>Epic Booster</strong>
                                                <p>Unlocks all Epic traits to be used by your Flovatar.</p>
                                            </div>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="/marketplace#templatecat=boost&rarity=legendary&sortby=pricelow">
                                            <img src="/images/booster-legendary.svg" alt="">
                                            <span class="data" x-bind:class="rarityCountColor('legendary')" x-text="rarityFullCount('legendary')"></span>
                                            <div class="info-tooltip">
                                                <strong>Legendary Booster</strong>
                                                <p>Unlocks all Legendary traits to be used by your Flovatar.</p>
                                            </div>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-wrapper jshidden" x-show="showAvailableResults">
            <div class="modal-content">
                <div class="modal-inner">
                    <div class="msg-box" x-bind:class="(!showMintBtn || !hasAllComponents()) ? 'error' : ''">
                        <div x-show="!showMintBtn">
                            <h2>Combination unavailable</h2>
                            <p>Sorry but someone else already picked this combination before you.</p>
                            <span class="note">Please try another one</span>
                        </div>
                        <div x-show="showMintBtn && !hasAllComponents()">
                            <h2>Missing Spark or Rarity Boosters</h2>
                            <p>The combination you selected is available, but you don't have the necessary Spark or Rarity Boosters to create it.</p>
                            <span class="note" x-show="!isDapperWallet()">If you want you can buy what you're missing from the Marketplace by clicking on the button below.</span>

                            <table class="builder-checkout-table" x-show="!isDapperWallet()">
                                <tr>
                                    <th>Name</th>
                                    <th>Rarity</th>
                                    <th class="price">Price<span class="sm"> (Flow)</span> </th>
                                    <th class="sm-td">Link</th>
                                </tr>
                                <template x-if="checkoutCart">
                                    <template x-for="(cartItem,index) in checkoutCart" :key="index">
                                        <tr>
                                            <td>
                                                <div class="desc-wrap" x-text="getTemplateCategoryById(cartItem.flow_metadata.templateId)"></div>
                                            </td>
                                            <td x-text="getTemplateRarityById(cartItem.flow_metadata.templateId)">
                                            </td>
                                            <td class="price" x-text="formatPrice(cartItem.price) + ' F'"></td>
                                            <td class="sm-td"><a x-bind:href="'/components/'+cartItem.flow_id+'/'+cartItem.flow_address" class="icon-link" target="_blank"></a></td>
                                        </tr>
                                    </template>
                                </template>
                            </table>
                        </div>
                        <div x-show="showMintBtn && hasAllComponents()">
                            <h2>Combination available</h2>
                            <p>
                                The combination you selected is available.<br>
                                You're all set to create your very own Flovatar!
                            </p>
                            <span class="note" x-show="checkoutTotal() > 0 && !isDapperWallet()">Please note that you don't own the following Flobits and they will be ignored unless you buy them from the Marketplace. You can also add them later if you want.</span>
                            <template x-if="checkoutCart && checkoutTotal() > 0 && !isDapperWallet()">
                                <table class="builder-checkout-table">
                                    <tr>
                                        <th>Name</th>
                                        <th>Rarity</th>
                                        <th class="price">Price<span class="sm"> (Flow)</span> </th>
                                        <th class="sm-td">Link</th>
                                    </tr>
                                    <template x-for="(cartItem,index) in checkoutCart" :key="index">
                                        <tr>
                                            <td>
                                                <div class="desc-wrap" x-text="getTemplateNameById(cartItem.flow_metadata.templateId)"></div>
                                            </td>
                                            <td x-text="getTemplateRarityById(cartItem.flow_metadata.templateId)">
                                            </td>
                                            <td class="price" x-text="formatPrice(cartItem.price) + ' F'"></td>
                                            <td class="sm-td"><a x-bind:href="'/components/'+cartItem.flow_id+'/'+cartItem.flow_address" class="icon-link" target="_blank"></a></td>
                                        </tr>
                                    </template>
                                </table>
                            </template>
                        </div>
                        <div class="btn-hold">
                            <button @click.prevent.stop="startBuilderCheckout(); showAvailableResults = false;" x-show="showMintBtn && (checkoutTotal() > 0)  && !isDapperWallet()" class="btn btn-green btn-sm btn-buy"><span class="inner" x-text="'Buy for '+checkoutTotal()+'F'"></span></button>
                            <button @click.prevent.stop="showAvailableResults = false" x-bind:class="(showMintBtn && hasAllComponents()) ? 'btn-green' : 'btn-red'" class="btn btn-sm"><span class="inner" x-text="(showMintBtn && hasAllComponents()) ? 'OK' : 'Close'"></span></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-wrapper jshidden" x-show="showMinBalance">
            <div class="modal-content">
                <div class="modal-inner">
                    <div class="msg-box error">
                        <h2>Not enough Storage</h2>
                        <p>Please make sure to have at lease 0,05 FLOW in your wallet to have enough storage space in your account for your Flovatar.</p>
                        <span class="note">You can read about <a href="https://docs.onflow.org/concepts/storage/" target="_blank" class="link">Storage on Flow here</a>.</span>
                        <div class="btn-hold">
                            <button @click.prevent.stop="showMinBalance = false" class="btn btn-sm btn-red"><span class="inner">OK</span></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-wrapper jshidden" x-show="showFlovatarCreated">
            <div class="modal-content">
                <div class="modal-inner">
                    <div class="msg-box">
                        <h2>Congratulations</h2>
                        <p>You just gave life to your very own Flovatar!</p>

                        <span class="note">Please take a look at how cool he/she is.</span>
                        <div class="btn-hold">
                            <a x-bind:href="'/collection/'+address()" class="btn btn-sm btn-green"><span class="inner">Go to your Collection</span></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-wrapper jshidden" x-show="showCheckoutCompleted">
            <div class="modal-content">
                <div class="modal-inner">
                    <div class="msg-box">
                        <h2>Congratulations</h2>
                        <p>You just completed the purchase and you have now all you need to create your Flovatar!</p>

                        <span class="note">Please click the "Create Flovatar" button after closing this modal.</span>
                        <div class="btn-hold">
                            <button @click.prevent.stop="showCheckoutCompleted = false" class="btn btn-sm btn-green"><span class="inner">OK</span></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>


    </div>

</x-guest-layout>
