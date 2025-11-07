<x-guest-layout>
    <div class="flovatar-builder fub-builder"
         x-data="fubData()"
         x-init="initFub($dispatch);"
         @cloudinary-loaded.window="cloudinaryLoaded($event.detail.id)"
    >

        

        <div class="flovatar-builder_frame fub-builder">

            <!-- PROGRESS BAR --------------------------------------->
            <div class="progress-bar-wrapper">
                <div class="progress-bar">
                    <div class="fub-btn step-back" x-show="activeTab > 1" @click.prevent.stop="moveTab(-1)"><i class="icon-chevron-left"></i></div>
                    <div class="fub-btn step" x-bind:class="{'active' : activeTab == 1, 'complete' : activeTab > 1}" @click.prevent.stop="setActiveTab(1)">1</div>
                    <div class="step-title" x-show="activeTab == 1">
                        Step 1 Title
                        <div class="fub-btn round show" @click.prevent.stop="showInfo = true"><i class="icon-info-simple"></i></div>
                    </div>
                    <div class="fub-btn step" x-bind:class="{'active' : activeTab == 2, 'complete' : activeTab > 2}" @click.prevent.stop="setActiveTab(2)">2</div>
                    <div class="step-title" x-show="activeTab == 2">
                        Step 2 Title
                        <div class="fub-btn round show" @click.prevent.stop="showInfo = true"><i class="icon-info-simple"></i></div>
                    </div>
                    <div class="fub-btn step" x-bind:class="{'active' : activeTab == 3, 'complete' : activeTab > 3}" @click.prevent.stop="setActiveTab(3)">3</div>
                    <div class="step-title" x-show="activeTab == 3">
                        Step 3 Title
                        <div class="fub-btn round show" @click.prevent.stop="showInfo = true"><i class="icon-info-simple"></i></div>
                    </div>
                    <div class="fub-btn step-next" @click.prevent.stop="moveTab(1)"><i class="icon-chevron-right"></i></div>
                </div>
            </div>
            <div class="flovatar-builder_content">

                <div class="builder-wrapper">

                    <!-- TAB 1 --------------------------------------->
                    <div id="tab-1" class="tab" x-show="activeTab == 1">
                        <div class="tab-inner">
                            <div class="section-title">
                                Collection Settings
                            </div>
                            <div class="tab-inner-content">

                                <div class="form-col">   
                                    <div class="form-row">
                                        <span class="form-label">Thumbnail</span>
                                        <div class="form-upload">
                                            <div class="inner">
                                                <i class="icon-upload"></i>
                                                <span>Upload file</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <span class="form-label">Collection Name</span>
                                        <div class="form-input">
                                            <input type="text">
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <span class="form-label">Author</span>
                                        <div class="form-input">
                                            <input type="text">
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <span class="form-label">Description</span>
                                        <div class="form-input">
                                            <input type="text">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-col">
                                     <div class="form-row">
                                        <span class="form-label">Token</span>
                                        <div class="form-input">
                                            <input type="text">
                                        </div>
                                        <div class="form-icon">
                                            <span class="fub-btn round show fluid" @click.prevent.stop="showInfo = true"><i class="icon-info-simple"></i></span>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <span class="form-label">Default Price</span>
                                        <div class="form-input small">
                                            <input type="text">
                                        </div>
                                        <div class="form-icon">
                                            <span class="fub-btn round show fluid" @click.prevent.stop="showInfo = true"><i class="icon-info-simple"></i></span>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <span class="form-label">Total Mints</span>
                                        <div class="form-input small">
                                            <input type="text">
                                        </div>
                                        <div class="form-icon">
                                            <span class="fub-btn round show fluid" @click.prevent.stop="showInfo = true"><i class="icon-info-simple"></i></span>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <span class="form-label">Price Increase</span>
                                        <div class="form-input small">
                                            <input type="text">
                                        </div>
                                        <div class="form-icon">
                                            <span class="fub-btn round show fluid" @click.prevent.stop="showInfo = true"><i class="icon-info-simple"></i></span>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <span class="form-label">Available Slots</span>
                                        <div class="form-input small">
                                            <input type="text">
                                        </div>
                                        <div class="form-icon">
                                            <span class="fub-btn round show fluid" @click.prevent.stop="showInfo = true"><i class="icon-info-simple"></i></span>
                                        </div>
                                    </div>
                                </div>

                            </div>
                            <div class="tab-footer">
                                <button class="fub-btn"><span class="inner">Reset</span></button>
                                <button class="fub-btn"><span class="inner">Save</span></button>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2 --------------------------------------->
                    <div id="tab-2" class="tab" x-show="activeTab == 2">
                        <div class="parts-wrapper">
                            <div class="parts-left-wrapper categories-scroll-wrapper">
                                <div class="section-title">
                                    Layers
                                    <div class="fub-btn round show" @click.prevent.stop="showContextMenu($event.target, 'cm-layers')"><i class="icon-dots"></i></div>
                                </div>
                                
                                <div class="layers-inner-wrapper">

                                    <div class="scroll-shadows-y"></div>
                                    <div class="scroll-shadows-x"></div>
                                    <div class="scroll-buttons-x">
                                        <div class="scroll-left"><i class="icon-chevron-left"></i></div>
                                        <div class="scroll-right"><i class="icon-chevron-right"></i></div>
                                    </div>

                                    <div class="parts-categories scroll-hide scroll-x">
                                        
                                            
                                        
                                            <ul class="parts-categories-list">

                                                <template x-for="layer in layers" :key="layer.id">
                                                    <li>
                                                        <button class="layer-btn" @click.prevent.stop="selectLayer(layer.id)" x-bind:class="getLayerClass(layer)">
                                                            <div class="layer-thumb"></div>
                                                            <span class="txt" x-text="layer.name"></span>
                                                            <div class="fub-btn round center" @click.prevent.stop="showContextMenu($event.target, 'cm-layer')"><i class="icon-dots"></i></div>
                                                        </button>
                                                    </li>
                                                </template>
                                         
                                            </ul>

                                    </div>
                                </div>
                            </div>

                            <div class="items-right-wrapper">
                                <div class="item-info-wrapper">
                                    <div class="section-title">
                                        Layer Items
                                        <div class="fub-btn round show" @click.prevent.stop="showContextMenu($event.target, 'cm-items')"><i class="icon-dots"></i></div>
                                    </div>

                                    <div class="item-info preview" x-bind:class="infoBoxClass()">

                                        <div class="category-title">
                                            <strong class="title" x-text="getLayerName(activeLayer)"></strong>
                                         </div>
                                         <div class="item-image">
                                             <img x-bind:src="getTempUrl(activeLayer)" x-show="getTempUrl(activeLayer)" />
                                         </div>
                                         <div class="item-data-wrapper">
                                            <div class="item-data">
                                                <dl>
                                                    <dt>Item Name</dt>
                                                    <dd x-text="getTemplateName()"></dd>
                                                </dl>
                                                <dl class="two-cols">
                                                    <dt>Item Max Mint</dt>
                                                    <dd>1000</dd>
                                                </dl>
                                                <dl>
                                                    <dt>Item Price <span class="small" x-text="templateSettings.currency">(Flow)</span></dt>
                                                    <dd>50.00</dd>
                                                </dl>
                                            </div>
                                         </div>
                                        <div class="fub-btn round"><i class="icon-dots"></i></div>

                                    </div>
                                </div>

                                <div class="parts-filters-wrapper filters-scroll-wrapper" x-show="showSportFilter() || showColorFilter()" >
                                    <div class="scroll-shadows-x"></div>
                                    <div class="scroll-buttons-x">
                                        <div class="scroll-left"><i class="icon-chevron-left"></i></div>
                                        <div class="scroll-right"><i class="icon-chevron-right"></i></div>
                                    </div>
                                    <div class="parts-filters scroll-hide scroll-x">

                                        <!-- Color Filters ///////////// -->
                                        <div class="parts-filter-list color-filter" x-show="showColorFilter()">
                                            <div class="btn-wrap">
                                                <button x-bind:class="filterColor == 'all' ? 'active' : ''" @click.prevent.stop="filterColor = 'all';"><img src="/images/filter-color-all.svg" /></button>
                                            </div>
                                            <div class="btn-wrap">
                                                <button x-bind:class="filterColor == 'Black' ? 'active' : ''" @click.prevent.stop="filterColor = 'Black';"><img src="/images/filter-color-black.svg" /></button>
                                            </div>
                                            <div class="btn-wrap">
                                                <button x-bind:class="filterColor == 'Blonde' ? 'active' : ''" @click.prevent.stop="filterColor = 'Blonde';"><img src="/images/filter-color-blonde.svg" /></button>
                                            </div>
                                            <div class="btn-wrap">
                                                <button x-bind:class="filterColor == 'Brown' ? 'active' : ''" @click.prevent.stop="filterColor = 'Brown';"><img src="/images/filter-color-brown.svg" /></button>
                                            </div>
                                            <div class="btn-wrap">
                                                <button x-bind:class="filterColor == 'Other' ? 'active' : ''" @click.prevent.stop="filterColor = 'Other';"><img src="/images/filter-color-other.svg" /></button>
                                            </div>
                                        </div>    

                                        <!-- Sports Filters ///////////// -->
                                        <div class="parts-filter-list sport-filter" x-show="showSportFilter()" >
                                            <div class="btn-wrap">
                                                <button x-bind:class="filterSport == 'all' ? 'active' : ''" @click.prevent.stop="filterSport = 'all';"><img src="/images/filter-all-sports.svg" /></button>
                                            </div>
                                            <div class="btn-wrap">
                                                <button x-bind:class="filterSport == 'Football' ? 'active' : ''" @click.prevent.stop="filterSport = 'Football';"><img src="/images/filter-football.svg" /></button>
                                            </div>
                                            <div class="btn-wrap">
                                                <button x-bind:class="filterSport == 'Basketball' ? 'active' : ''" @click.prevent.stop="filterSport = 'Basketball';"><img src="/images/filter-basketball.svg" /></button>
                                            </div>
                                            <div class="btn-wrap">
                                                <button x-bind:class="filterSport == 'American Football' ? 'active' : ''" @click.prevent.stop="filterSport = 'American Football';"><img src="/images/filter-american-football.svg" /></button>
                                            </div>
                                            <div class="btn-wrap">
                                                <button x-bind:class="filterSport == 'Team' ? 'active' : ''" @click.prevent.stop="filterSport = 'Team';"><img src="/images/filter-team-sports.svg" /></button>
                                            </div>
                                            <div class="btn-wrap">
                                                <button x-bind:class="filterSport == 'Motorsports' ? 'active' : ''" @click.prevent.stop="filterSport = 'Motorsports';"><img src="/images/filter-motorsports.svg" /></button>
                                            </div>
                                            <div class="btn-wrap">
                                                <button x-bind:class="filterSport == 'Combat' ? 'active' : ''" @click.prevent.stop="filterSport = 'Combat';"><img src="/images/filter-combat-sports.svg" /></button>
                                            </div>
                                            <div class="btn-wrap">
                                                <button x-bind:class="filterSport == 'Summer' ? 'active' : ''" @click.prevent.stop="filterSport = 'Summer';"><img src="/images/filter-summer-sports.svg" /></button>
                                            </div>
                                            <div class="btn-wrap">
                                                <button x-bind:class="filterSport == 'Winter' ? 'active' : ''" @click.prevent.stop="filterSport = 'Winter';"><img src="/images/filter-winter-sports.svg" /></button>
                                            </div>
                                            <div class="btn-wrap">
                                                <button x-bind:class="filterSport == 'Other' ? 'active' : ''" @click.prevent.stop="filterSport = 'Other';"><img src="/images/filter-other-sports.svg" /></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="parts-traits-wrapper">
                                    <div class="scroll-shadows-y"></div>
                                    <div class="scroll-shadows-x"></div>
                                    <div class="parts-traits scroll-hide">

                                        <ul class="parts-traits-list" >

                                            <template x-for="template in filterTemp(activeLayer)" :key="template.id">
                                                <li x-bind:class="getComponentParentClass(template)">
                                                    <button class="item-card item-card-add">
                                                        <!--
                                                        <div class="builder-add-template-btn">+ Add Template</div>
                                                        <div class="builder-add-template-btn-hover" @click.prevent.stop="openCloudinary()">Vectorize Image</div>
                                                        <div class="builder-add-template-btn-hover" @click.prevent.stop="uploadTemplate(activeLayer);">Upload Image</div>
                                                        <div class="builder-add-template-btn-hover" @click.prevent.stop="addTemplate(activeLayer); showEditTemplate = true;">Draw Image</div>
                                                        <input class="upload-fub-image" id="upload-fub-image" name="upload-fub-image" type="file" accept="image/*" multiple="multiple" x-on:change="processUploadFile">
                                                        -->
                                                        <div class="fub-btn round" @click.prevent.stop="showContextMenu($event.target, 'cm-item')"><i class="icon-dots"></i></div>
                                                    </button>

                                                </li>
                                            </template>

                                        </ul>

                                    </div>
                                </div>
                            </div>
                        </div>



                        <div class="machine-wrapper">
                            <div class="section-title">
                                Artwork
                                <div class="fub-btn round show" @click.prevent.stop="showContextMenu($event.target, 'cm-artwork')"><i class="icon-dots"></i></div>
                            </div>
                            <div class="artwork-wrapper">

                                <div class="artwork-display">
                                    <div class="builder-svg" x-html="getFullSvg()" id="sportvatar-builder-box"></div>
                                    
                                </div>
                            </div>
                            <div class="info-bar-wrapper">
                                
                                <div class="info-bar preview">
                                     
                                    <div class="segment">
                                        <ul class="btns-list">
                                            <li>
                                                <button @click.prevent.stop="randomizeCombination()" class="fub-btn"><span class="inner">Randomize</span></button>
                                            </li>
                                            <li>
                                                <button @click.prevent.stop="saveSvg()" class="fub-btn"><span class="inner">SVG <i class="icon-camera"></i></span></button>
                                            </li>
                                            <li>
                                                <button @click.prevent.stop="savePng()" class="fub-btn"><span class="inner">PNG <i class="icon-camera"></i></span></button>

                                                 <div class="info-tooltip" x-show="someValue">
                                                    <div class="tooltip-inner">
                                                        <i class="icon-warning"></i>
                                                        <span x-text="'Info tooltip text'"></span>
                                                    </div>
                                                </div>
                                            </li>
                                            <li>
                                               
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="segment small">
                                        <div class="info-inner">
                                            <span class="info-label">Price</span>
                                            <span class="info">50.00</span>
                                        </div>
                                    </div> 
                                </div>

                                <div class="info-bar">
                                    <div class="segment">
                                        <div class="info-inner">
                                            <span class="info-label">Name</span>
                                            <span class="info">Name of Item</span>
                                        </div>
                                    </div>
                                    <div class="segment small">
                                        <div class="info-inner">
                                            <span class="info-label">Max Mint</span>
                                            <span class="info">1000</span>
                                        </div>
                                    </div>
                                    <div class="segment small">
                                        <div class="info-inner">
                                            <span class="info-label">Price</span>
                                            <span class="info">50.00</span>
                                        </div>
                                    </div>
                                </div>



                            </div>
                            
                        </div>
                    </div>
                    <!-- TAB 3 --------------------------------------->
                     <div id="tab-3" class="tab" x-show="activeTab == 3">
                        <div class="tab-inner">
                            <div class="section-title">
                                Collection Settings
                            </div>
                            <div class="tab-inner-content">

                                <div class="form-col">   
                                    <div class="form-row">
                                        <span class="form-label">Thumbnail</span>
                                        <div class="form-upload">
                                            <div class="inner">
                                                <i class="icon-upload"></i>
                                                <span>Upload file</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <span class="form-label">Collection Name</span>
                                        <div class="form-input">
                                            <input type="text">
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <span class="form-label">Author</span>
                                        <div class="form-input">
                                            <input type="text">
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <span class="form-label">Description</span>
                                        <div class="form-input">
                                            <input type="text">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-col">
                                     <div class="form-row">
                                        <span class="form-label">Token</span>
                                        <div class="form-input">
                                            <input type="text">
                                        </div>
                                        <div class="form-icon">
                                            <span class="fub-btn round show fluid" @click.prevent.stop="showInfo = true"><i class="icon-info-simple"></i></span>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <span class="form-label">Default Price</span>
                                        <div class="form-input small">
                                            <input type="text">
                                        </div>
                                        <div class="form-icon">
                                            <span class="fub-btn round show fluid" @click.prevent.stop="showInfo = true"><i class="icon-info-simple"></i></span>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <span class="form-label">Total Max Mint</span>
                                        <div class="form-input small">
                                            <input type="text">
                                        </div>
                                        <div class="form-icon">
                                            <span class="fub-btn round show fluid" @click.prevent.stop="showInfo = true"><i class="icon-info-simple"></i></span>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <span class="form-label">Price Increase</span>
                                        <div class="form-input small">
                                            <input type="text">
                                        </div>
                                        <div class="form-icon">
                                            <span class="fub-btn round show fluid" @click.prevent.stop="showInfo = true"><i class="icon-info-simple"></i></span>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <span class="form-label">Groups</span>
                                        <div class="form-input small">
                                            <input type="text">
                                        </div>
                                        <div class="form-icon">
                                            <span class="fub-btn round show fluid" @click.prevent.stop="showInfo = true"><i class="icon-info-simple"></i></span>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <span class="form-label">Items</span>
                                        <div class="form-input small">
                                            <input type="text">
                                        </div>
                                        <div class="form-icon">
                                            <span class="fub-btn round show fluid" @click.prevent.stop="showInfo = true"><i class="icon-info-simple"></i></span>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <span class="form-label">Total Items</span>
                                        <div class="form-input small">
                                            <input type="text">
                                        </div>
                                        <div class="form-icon">
                                            <span class="fub-btn round show fluid" @click.prevent.stop="showInfo = true"><i class="icon-info-simple"></i></span>
                                        </div>
                                    </div>
                                </div>

                            </div>
                            <div class="tab-footer">
                                <button class="fub-btn"><span class="inner">Publish</span></button>
                            </div>
                        </div>
                    </div>
                    <!-- TAB 4 --------------------------------------->
                    <div id="tab-4" class="tab" x-show="activeTab == 4">
                        <div class="tab-inner">
                            Tab 4
                        </div>
                    </div>
                </div>



            </div>

            
        </div>




        <!-- CONTEXT MENUS ------------------------------>

        <div id="cm-item" class="context-menu" x-ref="cm-item" @click.outside="hideContextMenus()">
            <ul>
                <li @click.prevent.stop="editTemplate();">
                    <i class="icon-settings-cog"></i>
                    <span>Settings</span>
                </li>
                <li>
                    <i class="icon-draw"></i>
                    <span>Draw</span>
                </li>
                <li>
                    <i class="icon-upload"></i>
                    <span>Upload</span>
                </li>
                <li>
                    <i class="icon-vectorize"></i>
                    <span>Vectorize</span>
                </li>
                <li>
                    <i class="icon-move"></i>
                    <span>Move To</span>
                </li>
                <li>
                    <i class="icon-add-to-group"></i>
                    <span>Add to Group</span>
                </li>
                <li @click.prevent.stop="deleteActiveTemplate();">
                    <i class="icon-delete"></i>
                    <span>Delete</span>
                </li>
                <li>
                    <i class="icon-info"></i>
                    <span>Info</span>
                </li>
            </ul>
        </div>

        <div id="cm-layer" class="context-menu" x-ref="cm-layer" @click.outside="hideContextMenus()">
            <ul>
                <li @click.prevent.stop="editLayer();">
                    <i class="icon-settings-cog"></i>
                    <span>Settings</span>
                </li>
                <li>
                    <i class="icon-up-arrow"></i>
                    <span>Move Up</span>
                </li>
                <li>
                    <i class="icon-down-arrow"></i>
                    <span>Move Down</span>
                </li>
                <li>
                    <i class="icon-chain-link"></i>
                    <span>Make Dependent</span>
                </li>
                <li>
                    <i class="icon-delete"></i>
                    <span>Delete</span>
                </li>
                <li>
                    <i class="icon-info"></i>
                    <span>Info</span>
                </li>
            </ul>
        </div>

        <div id="cm-layers" class="context-menu" x-ref="cm-layers" @click.outside="hideContextMenus()">
            <ul>
                <li @click.prevent.stop="editLayer();">
                    <i class="icon-add-item"></i>
                    <span>Add Layer</span>
                </li>
                <li>
                    <i class="icon-delete"></i>
                    <span>Delete</span>
                </li>
                <li>
                    <i class="icon-info"></i>
                    <span>Info</span>
                </li>
            </ul>
        </div>

        <div id="cm-items" class="context-menu" x-ref="cm-items" @click.outside="hideContextMenus()">
            <ul>
                <li>
                    <i class="icon-add-item"></i>
                    <span>Add to Item</span>
                </li>
                <li>
                    <i class="icon-upload"></i>
                    <span>Bulk Upload</span>
                </li>
                <li>
                    <i class="icon-add-to-group"></i>
                    <span>Add to Group</span>
                </li>
                <li>
                    <i class="icon-delete"></i>
                    <span>Delete All</span>
                </li>
                <li>
                    <i class="icon-info"></i>
                    <span>Info</span>
                </li>
            </ul>
        </div>

        <div id="cm-artwork" class="context-menu" x-ref="cm-artwork" @click.outside="hideContextMenus()">
            <ul>
                <li>
                    <i class="icon-settings-cog"></i>
                    <span>Settings</span>
                </li>
                <li>
                    <i class="icon-draw"></i>
                    <span>Draw</span>
                </li>
                <li>
                    <i class="icon-upload"></i>
                    <span>Upload</span>
                </li>
                <li>
                    <i class="icon-reset"></i>
                    <span>Reset</span>
                </li>
                <li>
                    <i class="icon-guide"></i>
                    <span>Add Guide</span>
                </li>
                <li>
                    <i class="icon-vectorize"></i>
                    <span>Vectorize</span>
                </li>
                <li>
                    <i class="icon-delete"></i>
                    <span>Delete All</span>
                </li>
                <li>
                    <i class="icon-info"></i>
                    <span>Info</span>
                </li>
            </ul>
        </div>

        <div class="modal-wrapper fub-modal jshidden modal-info" x-show="showInfo">
            <div class="modal-content" @click.prevent.stop="showInfo = false">

                    <div class="modal-inner" @click.prevent.stop="showInfo = true">
                        <div class="fub-msg-box">
                            <div class="modal-title">
                                <span>Info Title</span>
                                <div class="fub-btn clear" @click.prevent.stop="showInfo = false"><i class="icon-close"></i></div>
                            </div>
                            <div class="modal-inner-content">
                                <span class="modal-header">Content header</span>
                                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut venenatis metus sed viverra commodo. Sed aliquet nisl diam, ac interdum nunc convallis et. Sed eu vehicula quam, a semper nibh. </p>

                                <span class="modal-header">Content header</span>
                                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut venenatis metus sed viverra commodo. Sed aliquet nisl diam, ac interdum nunc convallis et. Sed eu vehicula quam, a semper nibh. </p>
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
            <div class="modal-content" @click.prevent.stop="showEditLayer = false">
                <template x-if="tempEditLayer">
                    <div class="modal-inner" @click.prevent.stop="showEditLayer = true">
                        <div class="fub-msg-box">
                            <div class="modal-title">
                                <span>Layer Settings</span>
                                 <div class="fub-btn clear" @click.prevent.stop="showEditLayer = false"><i class="icon-close"></i></div>
                            </div>
                            <div class="modal-inner-content">
                                <div class="form-row">
                                    <span class="form-label">Name</span>
                                    <div class="form-input">
                                        <input type="text" x-model="tempEditLayer.name">
                                    </div>
                                    <div class="form-icon">
                                        <span class="fub-btn round show fluid" @click.prevent.stop="showInfo = true"><i class="icon-info-simple"></i></span>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <span class="form-label">Thumbnail</span>
                                    <div class="form-upload">
                                        <div class="inner">
                                            <i class="icon-upload"></i>
                                            <span>Upload file</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button @click.prevent.stop="showEditLayer = false" class="fub-btn"><span class="inner">Cancel</span></button>
                                <button @click.prevent.stop="saveLayerSettings()" class="fub-btn"><span class="inner">Save</span></button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <div class="modal-wrapper fub-modal jshidden" x-show="showEditTemplateSettings">
            <div class="modal-content" @click.prevent.stop="showEditTemplateSettings = false">
                <template x-if="tempEditTemplate">
                    <div class="modal-inner" @click.prevent.stop="showEditTemplateSettings = true">
                        <div class="fub-msg-box">
                            <div class="modal-title">
                                <span>Item Settings</span>
                                <div class="fub-btn clear" @click.prevent.stop="showEditTemplateSettings = false"><i class="icon-close"></i></div>
                            </div>
                            <div class="modal-inner-content">
                                <div class="form-row">
                                    <span class="form-label">Name</span>
                                    <div class="form-input">
                                        <input type="text" x-model="tempEditTemplate.name">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <span class="form-label">Price</span>
                                    <div class="form-input small">
                                        <input type="number" min="0.01" x-model="tempEditTemplate.price">
                                    </div>
                                    <div class="form-icon">
                                        <span class="fub-btn round show fluid" @click.prevent.stop="showInfo = true"><i class="icon-info-simple"></i></span>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <span class="form-label">Max Mint</span>
                                    <div class="form-input small">
                                        <input type="number" min="1" x-model="tempEditTemplate.maxMint">
                                    </div>
                                    <div class="form-icon">
                                        <span class="fub-btn round show fluid" @click.prevent.stop="showInfo = true"><i class="icon-info-simple"></i></span>
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
                            </div>
                            <div class="modal-footer">
                                <button @click.prevent.stop="showEditTemplateSettings = false" class="fub-btn"><span class="inner">Cancel</span></button>
                                <button @click.prevent.stop="saveTemplateSettings()" class="fub-btn"><span class="inner">Save</span></button>
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
