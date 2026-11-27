<div x-data="fubReport({{ (int) $fubId }}, null, {{ isset($funId) ? (int) $funId : 'null' }})" class="fub-report" style="margin-top:30px">
    <button class="isl-link" @click="open = true" data-fub="report">Report this FUB</button>
    <div class="fub-dialog" x-show="open" x-cloak @keydown.escape.window="open = false">
        <div>
            <h3>Report</h3>
            <template x-if="!sent">
                <div class="fub-form">
                    <label>Reason
                        <select x-model="reason"><option value="stolen-art">Stolen or copied art</option><option value="offensive">Offensive content</option><option value="scam">Scam or impersonation</option><option value="illegal">Illegal content</option><option value="other">Other</option></select>
                    </label>
                    <label>Details (optional)<textarea maxlength="1000" x-model="details"></textarea></label>
                    <p class="isl-error" x-show="error" x-text="error"></p>
                    <div class="fub-row"><button class="fub-btn" @click="send()">Send report</button><button class="fub-btn alt" @click="open = false">Cancel</button></div>
                </div>
            </template>
            <p x-show="sent" x-text="sent"></p>
        </div>
    </div>
</div>
