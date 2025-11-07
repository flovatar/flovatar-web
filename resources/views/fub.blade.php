<x-guest-layout>
    <div class="flovatar-builder fub-builder"
         x-data="fubData()"
         x-init="initFub($dispatch);"
         @cloudinary-loaded.window="cloudinaryLoaded($event.detail.id)"
    >
        <div class="flovatar-builder_bg"></div>
        <div class="flovatar-builder_frame">
            <div class="flovatar-builder_content">


                <div class="builder-content-wrapper">
                    <div class="core-parts">
                        <div class="widget-frame">
                            <span class="triangle-icon scrl-up"></span>
                            <div class="widget-content scrollable-frame">
                                <div class="scroll-y">
                                    <div class="scroll-content">
                                        <ul class="core-parts-list">
                                            <template x-for="layer in layers" :key="layer.id">
                                                <li>
                                                    <button class="btn part-btn" @click.prevent.stop="selectLayer(layer.id)" x-bind:class="getLayerClass(layer)">
                                                        <span class="inner">
                                                            <span class="txt" x-text="layer.name"></span>
                                                        </span>
                                                    </button>
                                                </li>
                                            </template>
                                            <li x-show="!fubPreviewMode">
                                                <button class="btn part-btn add-btn" @click.prevent.stop="addLayer(); editLayer();">
                                                        <span class="inner">
                                                            <span class="txt"> + Add Layer</span>
                                                        </span>
                                                </button>
                                            </li>
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
                                <div class="flovatar-info-row epic border-red">
                                    <strong class="title" x-text="getLayerName(activeLayer)" @click.prevent.stop="editLayer()"></strong>
                                    <div class="flovatar">
                                        <div class="inner-wrap" x-html="getTemplateSvg()"></div>
                                    </div>
                                    <div class="desc">
                                        <dl>
                                            <dt>Name</dt>
                                            <dd x-text="getTemplateName()"></dd>
                                            <dt x-show="!fubPreviewMode && activeTemplate">Admin</dt>
                                            <dd x-show="!fubPreviewMode && activeTemplate"><button @click.prevent.stop="editTemplate()" >Edit Settings</button></dd>
                                            <dd x-show="!fubPreviewMode && activeTemplate"><button @click.prevent.stop="showDeleteTemplate = true" >Delete Template</button></dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <span class="connector"></span>
                    </div>
                    <div class="flovatar-parts">
                        <div class="widget-frame">
                            <div class="parts-filter fub-settings" x-show="!fubPreviewMode">

                                <div class="btn-wrap btn-child"><button class="btn btn-sm btn-orange btn-icon"  @click.prevent.stop="showFubSettings = true"><span class="inner"><i class="icon-settings"></i></span></button></div>
                                <div class="btn-wrap btn-child"><button class="btn btn-sm btn-gray btn-icon"  @click.prevent.stop="saveJson()"><span class="inner">Download</span></button></div>
                                <div class="btn-wrap btn-child"><button class="btn btn-sm btn-gray btn-icon"  @click.prevent.stop="loadJson()"><span class="inner">Import</span></button></div>
                                <input class="upload-fub-json" id="upload-fub-json" name="upload-fub-json" type="file" accept="application/json" x-on:change="processUploadJson">
                            </div>
                            <div class="widget-content scrollable-frame">
                                <div class="scroll-y">
                                    <div class="scroll-content">
                                        <ul class="parts-list">
                                            <template x-for="template in filterTemp(activeLayer)" :key="template.id">
                                                <li>
                                                    <button class="part-card" @click.prevent.stop="selectTemplate(template.id)" x-bind:class="getTemplateClass(template.id)">
                                                        <div x-show="!template.png" class="builder-component-thumb" x-html="template.svg"></div>
                                                        <div x-show="template.png" class="builder-component-thumb">
                                                            <img x-bind:src="template.png" />
                                                        </div>
                                                        <div class="builder-component-info">
                                                            <div class="builder-component-price">
                                                                <span class="builder-component-price-amount" x-text="template.price"></span>
                                                                <span class="builder-component-price-token" x-text="templateSettings.currency"></span>
                                                            </div>
                                                            <div class="builder-component-mint">
                                                                <span class="builder-component-mint-number" x-text="template.maxMint"></span>
                                                                <span class="builder-component-mint-max">max.</span>
                                                            </div>
                                                        </div>
                                                    </button>
                                                </li>
                                            </template>
                                            <li x-show="!fubPreviewMode">
                                                <button class="part-card part-card-add">
                                                    <div class="builder-add-template-btn">+ Add Template</div>
                                                    <div class="builder-add-template-btn-hover" @click.prevent.stop="openCloudinary()">Vectorize Image</div>
                                                    <div class="builder-add-template-btn-hover" @click.prevent.stop="uploadTemplate(activeLayer);">Upload Image</div>
                                                    <div class="builder-add-template-btn-hover" @click.prevent.stop="addTemplate(activeLayer); showEditTemplate = true;">Draw Image</div>
                                                    <input class="upload-fub-image" id="upload-fub-image" name="upload-fub-image" type="file" accept="image/*" multiple="multiple" x-on:change="processUploadFile">
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <span class="connector"></span>
                    </div>
                </div>





                <div class="flovatar-machine">
                    <div class="widget-frame machine-frame no-bg">
                        <div class="widget-content machine-box no-bg">
                            <div class="machine">
                                <div class="flovatar-img">
                                    <div class="builder-svg" x-html="getFullSvg()" id="flovatar-builder-box"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <ul class="btns-list">
                        <li><button @click.prevent.stop="savePng()" class="btn btn-lilac btn-icon"><span class="inner"><i class="icon-camera"></i></span></button></li>
                        <li><button @click.prevent.stop="nextSkin()"  class="btn btn-lilac btn-tall"><span class="inner">Change Skin</span></button></li>
                        <li><button @click.prevent.stop="randomizeCombination()"  class="btn btn-lilac btn-tall"><span class="inner">Randomize</span></button></li>
                        <li><button @click.prevent.stop="fubPreviewMode = !fubPreviewMode"  class="btn btn-lilac btn-tall"><span class="inner" x-text="fubPreviewMode ? 'Edit Mode' : 'Preview Mode'"></span></button></li>
                        <li x-show="!fubPreviewMode"><button @click.prevent.stop="showResetTemplates = true"  class="btn btn-red btn-tall"><span class="inner">Reset Builder</span></button></li>
                        <li x-show="fubPreviewMode"><button @click.prevent.stop="demoMintNft()"  class="btn btn-green btn-tall"><span class="inner">Test Mint</span></button></li>
                        <li x-show="fubPreviewMode" class="fub-mint-price" x-html="getCurrentPriceFormatted()"></li>
                    </ul>
                    <div class="rarity-score fub-max-mint alt">
                        <div class="widget-frame circular">
                            <div class="widget-content">
                                <div class="inner-circle">
                                    <strong class="score" x-text="fubPreviewMode ? fubDemoSaleCount + ' ' : templateSettings.baseMaxMint"></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="fub-price alt">
                        <div class="widget-frame circular">
                            <div class="widget-content">
                                <div class="inner-circle">
                                    <strong class="score" x-text="fubPreviewMode ? fubDemoSaleTotalFormatted(): fubMintPrice()"></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="fub-revenue alt" x-show="!fubPreviewMode">
                        <div class="widget-frame circular">
                            <div class="widget-content">
                                <div class="inner-circle">
                                    <strong class="score" x-text="fubSoldOutValue()"></strong>
                                </div>
                            </div>
                        </div>
                    </div>




                </div>
            </div>
        </div>












        <div id="tldraw-wrapper" class="modal-wrapper fub-modal jshidden" x-show="showEditTemplate">
            <div class="modal-content">
                <div class="modal-inner">
                    <div class="msg-box">
                        <h2>Edit Template</h2>
                        <p>Here you can edit the template</p>
                        <div class="iframe-container">
                            <iframe id="tldraw-box" src="/tldraw/index.html#/export"></iframe>
                            <!--<div x-show="showDrawOverlay" class="draw-overlay"></div>-->
                        </div>
                        <div class="btn-hold">
                            <!--<button @click.prevent.stop="showDrawOverlay = !showDrawOverlay" class="btn btn-sm btn-default"><span class="inner">Toggle Overlay</span></button>-->
                            <button @click.prevent.stop="saveTemplate()" class="btn btn-sm btn-green"><span class="inner">Save</span></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-wrapper fub-modal jshidden" x-show="showEditLayer">
            <div class="modal-content">
                <template x-if="tempEditLayer">
                    <div class="modal-inner">
                        <div class="msg-box">
                            <h2>Edit Layer Settings</h2>
                            <p>Here you can edit the layer details</p>

                            <div class="select-wrap sm">
                                <span class="label">Name</span>
                                <div class="input-wrap">
                                    <input type="text" x-model="tempEditLayer.name">
                                </div>
                            </div>
                            <div class="btn-hold">
                                <button @click.prevent.stop="saveLayerSettings()" class="btn btn-sm btn-green"><span class="inner">Save</span></button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <div class="modal-wrapper fub-modal jshidden" x-show="showEditTemplateSettings">
            <div class="modal-content">
                <template x-if="tempEditTemplate">
                    <div class="modal-inner">
                        <div class="msg-box">
                            <h2>Edit Template Settings</h2>
                            <p>Here you can edit the template settings</p>
                            <div class="select-wrap sm">
                                <span class="label">Name</span>
                                <div class="input-wrap">
                                    <input type="text" x-model="tempEditTemplate.name">
                                </div>
                            </div>
                            <div class="select-wrap sm">
                                <span class="label">Price</span>
                                <div class="input-wrap">
                                    <input type="number" min="0.01" x-model="tempEditTemplate.price">
                                </div>
                            </div>
                            <div class="select-wrap sm">
                                <span class="label">Max Mint</span>
                                <div class="input-wrap">
                                    <input type="number" min="1" x-model="tempEditTemplate.maxMint">
                                </div>
                            </div>
                            <div class="select-wrap sm" x-show="tempEditTemplate.cloudinary">
                                <span class="label">SVG Colors</span>
                                <div class="input-wrap">
                                    <input type="number" min="2" max="30" x-model="tempEditTemplate.cldColors">
                                </div>
                            </div>
                            <div class="select-wrap sm" x-show="tempEditTemplate.cloudinary">
                                <span class="label">SVG Details</span>
                                <div class="input-wrap">
                                    <input type="number" min="0.1" max="0.9" step="0.1" x-model="tempEditTemplate.cldDetails">
                                </div>
                            </div>
                            <div class="select-wrap sm" x-show="tempEditTemplate.cloudinary">
                                <span class="label">SVG Corners</span>
                                <div class="input-wrap">
                                    <input type="number" min="1" max="100" x-model="tempEditTemplate.cldCorners">
                                </div>
                            </div>
                            <div class="btn-hold">
                                <button @click.prevent.stop="saveTemplateSettings()" class="btn btn-sm btn-green"><span class="inner">Save</span></button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <div class="modal-wrapper fub-modal jshidden" x-show="showFubSettings">
            <div class="modal-content">
                <template x-if="templateSettings">
                    <div class="modal-inner">
                        <div class="msg-box">
                            <h2>Edit FUB Settings</h2>
                            <p>Here you can edit the FUB settings</p>
                            <div class="select-wrap sm">
                                <span class="label">Name</span>
                                <div class="input-wrap">
                                    <input type="text" x-model="templateSettings.name">
                                </div>
                            </div>
                            <div class="select-wrap sm">
                                <span class="label">Default Price for Traits</span>
                                <div class="input-wrap">
                                    <input type="number" min="0.01" x-model="templateSettings.basePrice">
                                </div>
                            </div>
                            <div class="select-wrap sm">
                                <span class="label">Price increase after each trait</span>
                                <div class="input-wrap">
                                    <input type="number" min="0.0" x-model="templateSettings.traitsPriceIncrease">
                                </div>
                            </div>
                            <div class="select-wrap sm">
                                <span class="label">FUB Max Mint</span>
                                <div class="input-wrap">
                                    <input type="number" min="1" x-model="templateSettings.baseMaxMint">
                                </div>
                            </div>
                            <div class="select-wrap sm">
                                <span class="label">Token Currency</span>
                                <select x-model="templateSettings.currency">
                                    <option value="FLOW">FLOW</option>
                                    <option value="DUST">ÐUST</option>
                                    <option value="USDC">USDC</option>
                                </select>
                            </div>
                            <div class="btn-hold">
                                <button @click.prevent.stop="saveFubSettings()" class="btn btn-sm btn-green"><span class="inner">Save</span></button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <div class="modal-wrapper fub-modal jshidden" x-show="showDeleteTemplate">
            <div class="modal-content">
                <div class="modal-inner">
                    <div class="msg-box">
                        <h2>Delete Template</h2>
                        <p>Are you sure you want to delete this template?</p>

                        <div class="btn-hold">
                            <button @click.prevent.stop="showDeleteTemplate = false" class="btn btn-sm btn-green"><span class="inner">No, go back</span></button>
                            <button @click.prevent.stop="deleteActiveTemplate()" class="btn btn-sm btn-red"><span class="inner">Yes, Delete!</span></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-wrapper fub-modal jshidden" x-show="showResetTemplates">
            <div class="modal-content">
                <div class="modal-inner">
                    <div class="msg-box">
                        <h2>Reset Builder</h2>
                        <p>Are you sure you want to delete all the templates and completely reset the Builder?</p>

                        <div class="btn-hold btns-reset-fub">
                            <button @click.prevent.stop="showResetTemplates = false" class="btn btn-sm btn-lilac"><span class="inner">No, go back</span></button>
                            <button @click.prevent.stop="loadDemo()" class="btn btn-sm btn-red"><span class="inner">Load Demo</span></button>
                            <button @click.prevent.stop="resetTemplates()" class="btn btn-sm btn-red"><span class="inner">Delete All</span></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script type="text/javascript">

            var cloudNameCloudinary = <?php echo json_encode(Str::after(config('cloudinary.cloud_url'), '@'), 512) ?>;
            var uploadPresetCloudinary = <?php echo json_encode(config('cloudinary.upload_preset'), 15, 512) ?>;

            function openCloudinary() {
                window.cloudinary.openUploadWidget(
                    { cloud_name: cloudNameCloudinary,
                        upload_preset: uploadPresetCloudinary
                    },
                    (error, result) => {
                        if (!error && result && result.event === "success") {
                            console.log('Done uploading..');
                            console.log(result.info.public_id);
                            window.dispatch('cloudinary-loaded', {id: result.info.public_id});
                        }
                    }).open();
            }
        </script>

    </div>

</x-guest-layout>
