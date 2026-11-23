


<div id="moonpay-modal" x-show="showMoonpay" class="modal-wrapper jshidden">
    <div class="modal-content">
        <div class="modal-inner">
            <button  @click.prevent.stop="showMoonpay=false" class="close-moonpay"><span class="icon-close"></span></button>
            <template x-if="address() && showMoonpay">
                <iframe x-bind:src="moonpayUrl()" class="w-full h-full rounded-xl"></iframe>
            </template>
        </div>
    </div>
</div>


<div id="init-account-modal" class="modal-wrapper jshidden" x-show="showInitPopup">
    <div class="modal-content">
        <div class="modal-inner">
            <div class="msg-box">
                <h2>Ready to get your $DUST?</h2>
                <p>To be able to interact with the Flovatar world and receive the $DUST token, first you need to initialize your account.</p>

                <span class="note">Please approve the transaction to proceed.</span>
                <div class="btn-hold">
                    <button @click.prevent.stop="startAccountInit" class="btn btn-green"><span class="inner">Initialize account</span></button>
                </div>
            </div>
        </div>
    </div>
</div>



<div id="init-account-modal" class="modal-wrapper jshidden" x-show="showInitMetadataPopup">
    <div class="modal-content">
        <div class="modal-inner">
            <div class="msg-box">
                <h2>Metadata Views Update</h2>
                <p>To be able to make our Flovatars more discoverable by other projects, we update the smart contract to the latest Metadata Views standard that will provide informations about the image to display and also about royalties.</p>

                <span class="note">Please approve the transaction to proceed with the update of your public collection capability. This process won't touch your private storage and the NFTs will be totally safe.</span>
                <div class="btn-hold">
                    <button @click.prevent.stop="startAccountMetadataInit" class="btn btn-green"><span class="inner">Initialize Metadata Views</span></button>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="transaction-error-modal" x-show="showTransactionError" class="modal-wrapper jshidden">
    <div class="modal-content">
        <div class="modal-inner">
            <div class="msg-box error">
                <h2>Transaction Error</h2>
                <p>
                    Unfortunately your transaction could not be sealed and has been cancelled.<br>
                    No money or NFT has been transferred to anyone.
                </p>
                <span class="note"><button @click.prevent.stop="showTransactionErrorMessage = true" x-show="!showTransactionErrorMessage" class="underline">Click here to show the full message</button></span>

                <p x-show="showTransactionErrorMessage" x-text="transactionErrorMessage"></p>
                <p x-show="showTransactionErrorMessage">
                    <a x-bind:href="(txId) ? 'https://www.flowdiver.io/tx/'+txId : '#'" target="_blank">See transaction on Flowdiver</a>
                </p>
                <div class="btn-hold">
                    <button @click.prevent.stop="showTransactionError = false" class="btn btn-sm btn-red"><span class="inner">Close</span></button>
                </div>
            </div>
        </div>
    </div>
</div>



<div id="transaction-error-modal" x-show="showMaintenance" class="modal-wrapper jshidden">
    <div class="modal-content">
        <div class="modal-inner">
            <div class="msg-box error">
                <h2>Flow Blockchain maintenance in progress</h2>
                <p>
                    Right now the Flow Blockchain is undergoing some maintenance.<br>
                    Please wait a few hours to be able to use the Flovatar website.<br>
                    No transaction or script can be executed at the moment since all the access nodes to the blockchain have been turned off.<br>
                    Thanks for you patience!
                </p>

                <div class="btn-hold">
                    <button @click.prevent.stop="showMaintenance = false" class="btn btn-sm btn-red"><span class="inner">Close</span></button>
                </div>
            </div>
        </div>
    </div>
</div>



<div id="choose-wallet-modal" class="modal-wrapper jshidden" x-show="showWalletModal">
    <div class="modal-content">
        <div class="modal-inner">
            <div class="msg-box">
                <a href="#" class="close" @click.prevent.stop="showWalletModal = false"></a>
                <div class="heading">
                    <h2>Choose your wallet</h2>
                </div>
                <ul class="btn-list">
                    <li>
                        <button @click.prevent.stop="chooseWallet('flow')" class="btn-grad btn-grad-blue">
                            <span class="ico-wrap">
                                <img src="/images/icon-flow.svg" width="56" alt="">
                            </span>
                            Connect Flow Wallet
                        </button>
                    </li>
                    <li>
                        <button @click.prevent.stop="chooseWallet('dapper')" class="btn-grad btn-grad-purple">
                            <span class="ico-wrap">
                                <img src="/images/ico-dapper.png" width="56" alt="">
                            </span>
                            Connect Dapper
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>



<div id="transaction-error-modal" x-show="showBlacklistModal" class="modal-wrapper jshidden">
    <div class="modal-content">
        <div class="modal-inner">
            <div class="msg-box error">
                <h2>Access Denied</h2>
                <p>
                    If you're reading this, you should know that you did something really bad and dishonest that went against the interest of the project.
                </p>
                <p>
                    For this reason we won't allow you to use the Flovatar website like any other user.
                </p>
                <p>
                    Maybe next time you will think twice about your actions...
                </p>
                <div class="btn-hold">
                    <button @click.prevent.stop="showBlacklistModal = false" class="btn btn-sm btn-red"><span class="inner">Close</span></button>
                </div>
            </div>
        </div>
    </div>
</div>
