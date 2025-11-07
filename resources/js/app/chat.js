import Alpine from "alpinejs";
import {DateTime} from "luxon";
import Parallax from "parallax-js";
import {config} from "@onflow/fcl";
import {toSvg} from "jdenticon";

Alpine.data('chatData', (flovatar, templates, assistant, voices) => ({
    flovatar: flovatar,
    flovatarChain: null,
    templates: templates,
    assistant: assistant,
    voices: voices,
    editEnabled: false,
    audioReady: false,
    thread: null,
    run: null,
    message: '',
    lastMessageSent: '',
    curMouth: 436,
    mouths:[436,437,438,440,426],
    categories: ['body', 'clothing', 'nose', 'mouth', 'facialHair', 'hair', 'eyes', 'accessory', 'eyeglasses', 'hat', 'background'],
    messageTemp: [],
    messageList: [],
    audioPlayer: null,
    darkMode: Alpine.$persist(false),
    randomUserId: Alpine.$persist(Math.floor(Math.random() * 1000000000) + 1),
    showModal: false,
    isThinking: false,
    showSocial: false,
    curStep: 0,
    init() {
        //console.log(this.flovatar);
        if(this.darkMode){
            document.documentElement.classList.add('dark');
        }
        if(this.flovatar) {
            this.refreshMessages();
            this.audioPlayer = new Audio();
            this.resetMouth();
        }

        if(this.flovatar && this.flovatar.flow_id && this.flovatar.flow_address){
            window.getFlovatar(this.flovatar.flow_address, this.flovatar.flow_id);
            window.getFlovatarSale(this.flovatar.flow_address, this.flovatar.flow_id);
        }
        let self = this;
        setTimeout(() => {
            self.checkInit();
        },1000);

    },
    iconFolder(){
       return this.darkMode ? 'white' : 'black';
    },
    resetMouth(){
        let arrayTemplates = window.signatureToArray(this.flovatar.flow_metadata['metadata']['combination']);
        this.curMouth = arrayTemplates.mouth;
    },
    showEditBtn(){
        return this.flovatar && this.flovatar.flow_address && this.address() == this.flovatar.flow_address;
    },
    enableChatEdit(){
        window.flovatarUnlockChat(this.flovatar.flow_id)
    },
    loadFlovatarFromChain(flovatar){
        l('loadFlovatarFromChain');
        l(flovatar);
        this.flovatarChain = flovatar;
        if(flovatar && flovatar.bio && flovatar.bio['chat'] === 'true'){
            this.editEnabled = true;
        }
        //TODO: REMOVE THIS FOR LAUNCH!!!
        //this.editEnabled = true;
    },
    loadSaleFromChain(sale){
        l('loadSaleFromChain');
        l(sale);
        //this.flovatarChain = sale;
    },
    hasBackground(){
        return this.flovatar && this.flovatar.flow_metadata['backgroundId'];
    },
    sendMessage(){
        if(this.message && !this.isThinking){
            let address = this.address() ?? this.randomUserId;
            let self = this;
            this.isThinking = true;
            this.lastMessageSent = this.message;
            self.scrollToBottom();
            axios.post('/chat/send', {
                address: address,
                id: this.flovatar.flow_id,
                message: this.message
            })
                .then(function (response) {
                    //console.log('process response!!!');
                    //console.log(response);
                    self.run = response.data.run;
                    self.curStep = 1;
                    self.messageTemp = response.data.messages;
                    self.refreshMessages();
                    self.checkRun();
                })
                .catch(function (error) {
                    console.log(error);
                });

            this.message = '';
        }
    },

    checkInit(){
        //console.log('check init!!!');
        //console.log(this.flovatar);
        if(this.flovatar && this.flovatar.flow_id){
            let self = this;
            let address = this.address() ?? this.randomUserId;
            //console.log('check thread init timeout!!!');
            setTimeout(() => {
                axios.post('/chat/checkaddress', {
                    id: self.flovatar.flow_id,
                    address: address
                })
                    .then(function (response) {
                        //console.log('process thread init check!!!');
                        //console.log(response);
                        if(response && response.data && response.data.thread && response.data.messages){
                            self.thread = response.data.thread;
                            self.messageTemp = response.data.messages;
                            self.refreshMessages();
                        }
                    })
                    .catch(function (error) {
                        console.log(error);
                    });
            },1000);
        }
    },
    checkRun(){
        //console.log('check run!!!');
        //console.log(this.run);
        if(this.run && (this.run.status == 'queued' || this.run.status == 'in_progress')){
            let self = this;
            setTimeout(() => {
                axios.post('/chat/check', {
                    threadId: self.run.thread_id,
                    runId: self.run.id
                })
                    .then(function (response) {
                        //console.log('process thread check!!!');
                        //console.log(response);
                        if(response && response.data && response.data.thread && response.data.messages){
                            self.thread = response.data.thread;
                            self.run = response.data.run;
                            self.messageTemp = response.data.messages;
                            self.checkRun();
                        }
                    })
                    .catch(function (error) {
                        console.log(error);
                        self.checkRun();
                    });
            },1000);
        } else if(this.run && this.run.status == 'completed'){
            this.curStep = 2;
            this.refreshMessages();
            this.playLastMessage();
            this.audioReady = false;
        }
    },
    refreshMessages(){

        let tempMessages = _.reverse(_.map(this.messageTemp.data, (message) => {
            return {
                type: message.role == 'user' ? 'sent' : 'received',
                message: message.content[0].text.value,
                id: message.id
            }
        }));
        _.merge(this.messageList, tempMessages);

        this.scrollToBottom();
    },
    scrollToBottom(){
        this.$nextTick(() => {
            let el = document.getElementById('message-list');
            el.scrollTo(0, el.scrollHeight);
        });
    },
    messages(){
        return this.messageList;
    },
    toggleDarkMode() {
        this.darkMode = !this.darkMode;
        if (this.darkMode) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    },
    chatImg(){
        let id = this.flovatar ? this.flovatar.flow_id : 6;
        return 'https://images.flovatar.com/flovatar/png/'+id+'.png'
    },
    playLastMessage(){
        let self = this;
        if(this.messageList.length > 0) {
            axios.post('/chat/tts', {
                text: this.messageList[this.messageList.length - 1].message,
                assistant_id: this.assistant.id
            })
                .then(function (response) {
                    self.curStep = 3;
                    self.audioPlayer.src = 'data:audio/mp3;base64,' + response.data;
                    self.audioReady = true;
                    self.scrollToBottom();
                    self.playAudioPlayer();
                })
                .catch(function (error) {
                    console.log(error);
                });
        }
    },
    playAudioPlayer(){
        //console.log('playAudioPlayer');
        let self = this;
        self.audioPlayer.play();
        let interval = setInterval(function(){
            self.curMouth = self.mouths[Math.floor(Math.random() * self.mouths.length)];
            if(self.audioPlayer.paused){
                clearInterval(interval);
                self.resetMouth();
                self.isThinking = false;
                self.curStep = 0;
            }
        }, 100);
    },
    getFullSvg(){
        let arrayTemplates = window.signatureToArray(this.flovatar.flow_metadata['metadata']['combination']);
        arrayTemplates.mouth = this.curMouth;
        let svg = '';
        let self = this;
        _.each(this.categories, function(category){
            if(arrayTemplates[category]) {
                let temp = _.find(self.templates, { 'flow_id': parseInt(arrayTemplates[category],10)});
                svg += temp ? temp.svg : '';
            }
        });


        let background = _.find(self.templates, function(item){return item.flow_id == self.flovatar.flow_metadata['backgroundId'];}) ;
        background = background ? background.svg : '';
        let accessory =  _.find(self.templates, function(item){return item.flow_id == self.flovatar.flow_metadata['accessoryId'];});
        accessory = accessory ? accessory.svg : '';
        let hat =  _.find(self.templates, function(item){return item.flow_id == self.flovatar.flow_metadata['hatId'];});
        hat = hat ? hat.svg : '';
        let eyeglasses = _.find(self.templates, function(item){return item.flow_id == self.flovatar.flow_metadata['eyeglassesId'];});
        eyeglasses = eyeglasses ? eyeglasses.svg : '';

        return  window.wrapSvg(background+svg+accessory+eyeglasses+hat);
    },
    openEditor(){
        this.showModal = true;
    },
    saveAssistant(){
        let self = this;
        axios.post('/chat/update', {
            description: this.assistant.description,
            voice_id: this.assistant.voice_id,
            user_instructions: this.assistant.user_instruction,
            assistant_id: this.assistant.id
        })
            .then(function (response) {
                self.showModal = false;
            })
            .catch(function (error) {
                console.log(error);
            });
    },
    playVoice(){
        let curVoice = _.find(this.voices, { 'voice_id': this.assistant.voice_id});
        if(curVoice){
            this.audioPlayer.src = curVoice.preview_url;
            this.audioPlayer.play();
        }
    }

}));

window.initChat = function(dispatch){
    window.dispatch = dispatch;
}
