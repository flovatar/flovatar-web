


                        <div class="chat-controls-right">
                            <img src="/images/controls-right.svg" alt="" />
                        </div>
                    </div>
                </div>

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
                                <textarea x-bind:disabled="!editEnabled" x-model="assistant.description" class="w-full px-3 py-2 text-gray-700 border rounded-lg focus:outline-none" rows="4" placeholder="Enter description here"></textarea>
                            </div>

                            <div class="my-5">
                                <label class="pb-5">Instructions <span class="text-sm">(private)</span></label>
                                <textarea x-bind:disabled="!editEnabled" x-model="assistant.user_instruction" class="w-full px-3 py-2 text-gray-700 border rounded-lg focus:outline-none" rows="4" placeholder="Enter instructions here"></textarea>
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

            </div>


        </div>
    </div>

</body>
</html>
