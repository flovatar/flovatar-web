<?php

namespace App\Http\Controllers;

use App\Models\Flovatar;
use App\Models\GptAssistant;
use App\Models\GptThread;
use Flow;
use Illuminate\Http\Request;
use OpenAI\Laravel\Facades\OpenAI;
use Symfony\Component\Process\Exception\ProcessFailedException;


class GptController extends AuthController
{
    public function __invoke()
    {

        return view('gpt');

    }


    public function index()
    {

        $assistants = GptAssistant::whereNotNull('description')->orderBy('updated_at', 'desc')->with('flovatar')->limit(200)->get();


        return view('gpt-showroom')->with(['assistants' => $assistants->shuffle()]);
    }

    public function show($id)
    {

        $templates = $this->getTemplates();

        $flovatar = Flovatar::where('flow_id', $id)->first();
        if(!$flovatar){
            $flovatar = Flovatar::where('name', $id)->orderBy('flow_id', 'asc')->first();
        }

        if(!$flovatar){
            return view('gpt');
        }

        $assistant = GptAssistant::whereFlowId($flovatar->flow_id)->first();

        if(!$assistant){
            $name = $flovatar->name ?: 'Flovatar '.$flovatar->flow_id;
            $bio = isset($flovatar->flow_metadata['bio']) && isset($flovatar->flow_metadata['bio']['story']) ? ' Your Biography is the following: '.$flovatar->flow_metadata['bio']['story'] : '';
            $instructions = 'You are one of the 9,999 Flovatars that were sent on a mission to Earth. You arrived in the year 2021, in spark form, as described in the fiction story "Flovatar - A Dust Odyssey." You must always remain in character and behave like you are part of the Flovatar story world. You can invent an interesting story about how you crash landed on Earth and what you have discovered on your journey through Earth so far. You can travel to any country on Earth and any time period, including the past, present, and future. You can compare life on Earth to life on your home planet of Flova. Always keep the answers three sentences or less. Your name is '.$name.'. '.$bio;
            $assistantRes = OpenAI::assistants()->create([
                'instructions' => $instructions,
                'name' => $name,
                'tools' => [
                    [
                        'type' => 'file_search',
                    ],
                ],
                'tool_resources' => [
                    'file_search' => [
                        'vector_store_ids' => [
                            'vs_67b7d7ba01008191ae5e00538c7369f1'
                        ]
                    ]
                ],
                'model' => 'gpt-4o',
            ]);

            $assistant = GptAssistant::create([
                'gpt_id' => $assistantRes->id,
                'voice_id' => 'JBFqnCBsd6RMkjVDRZzb',
                'user_instruction' => '',
                'system_instruction' => $instructions,
                'suggestions' => [],
                'metadata' => $assistantRes->toArray(),
                'flow_id' => $flovatar->flow_id
            ]);
        }


        $elevenLabsClient = app()->make(\Georgehadjisavva\ElevenLabsClient\ElevenLabsClient::class);
        $voices = $elevenLabsClient->voices()->getAll();

        return view('gpt')->with(['flovatar' => $flovatar, 'templates' => $templates, 'assistant' => $assistant, 'voices' => $voices]);
    }
    public function sendMessage(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:2000',
        ]);
        $address = $this->chatIdentity($request);
        $message = $request->input('message');
        $id = $request->input('id');

        if(!$address){
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $flovatar = Flovatar::where('flow_id', $id)->first();
        if(!$flovatar){
            $flovatar = Flovatar::where('name', $id)->first();
        }

        $assistant = $flovatar ? GptAssistant::whereFlowId($flovatar->flow_id)->first() : null;

        if(!($flovatar && $assistant)){
            return response()->json(['error' => 'Not found'], 404);
        }


        $thread = GptThread::whereFlowAddress($address)->whereGptAssistantId($assistant->id)->first();

        if(!$thread){
            $threadApi = OpenAI::threads()->create([]);

            $thread = GptThread::create([
                'gpt_id' => $threadApi->id,
                'gpt_assistant_id' => $assistant->id,
                'flow_address' => $address,
                'metadata' => $threadApi->toArray()
            ]);
        }

        OpenAI::threads()->messages()->create($thread->gpt_id, [
            'role' => 'user',
            'content' => $message,
        ]);

        $run = OpenAI::threads()->runs()->create($thread->gpt_id, [
                'assistant_id' => $assistant->gpt_id,
            ],
        );

        $messages = OpenAI::threads()->messages()->list($thread->gpt_id, [
            'limit' => 5,
        ]);

        return ['run' => $run->toArray(), 'messages' => $messages->toArray()];
    }


    public function checkThreadByAddress(Request $request)
    {
        $address = $this->chatIdentity($request);
        $id = $request->input('id');

        if(!$address){
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $flovatar = Flovatar::where('flow_id', $id)->first();
        if(!$flovatar){
            $flovatar = Flovatar::where('name', $id)->first();
        }

        $assistant = $flovatar ? GptAssistant::whereFlowId($flovatar->flow_id)->first() : null;

        if(!($flovatar && $assistant)){
            return response()->json(['error' => 'Not found'], 404);
        }


        $thread = GptThread::whereFlowAddress($address)->whereGptAssistantId($assistant->id)->first();

        if(!$thread){
            $threadApi = OpenAI::threads()->create([]);

            $thread = GptThread::create([
                'gpt_id' => $threadApi->id,
                'gpt_assistant_id' => $assistant->id,
                'flow_address' => $address,
                'metadata' => $threadApi->toArray()
            ]);
        }


        $threadApi = OpenAI::threads()->retrieve($thread->gpt_id);
        $messages = OpenAI::threads()->messages()->list($thread->gpt_id, [
            'limit' => 10,
        ]);

        return ['messages' => $messages->toArray(), 'thread' => $threadApi->toArray()];
    }


    public function checkThread(Request $request)
    {
        $runId = $request->input('runId');
        $threadId = $request->input('threadId');

        $thread = GptThread::whereGptId($threadId)->first();
        if(!$thread || $thread->flow_address !== $this->chatIdentity($request)){
            return response()->json(['error' => 'Not found'], 404);
        }

        $threadApi = OpenAI::threads()->retrieve($threadId);
        $runApi = OpenAI::threads()->runs()->retrieve($threadId,$runId);
        $messages = OpenAI::threads()->messages()->list($threadId, [
            'limit' => 5,
        ]);

        return ['messages' => $messages->toArray(), 'thread' => $threadApi->toArray(), 'run' => $runApi->toArray()];
    }

    public function tts(Request $request){
        $request->validate([
            'text' => 'required|max:2000|string',
            'assistant_id' => 'required|numeric'
        ]);
        $assistant = GptAssistant::findOrFail($request->input('assistant_id'));

        $elevenLabs = new \ArdaGnsrn\ElevenLabs\ElevenLabs();
        $response = $elevenLabs->textToSpeech($assistant->voice_id, $request->input('text'), 'eleven_turbo_v2');
        return base64_encode($response->getResponse()->getBody()->getContents());
    }
    public function update(Request $request){

        $request->validate([
            'description' => 'max:140|string',
            'user_instructions' => 'max:20000|string',
            'voice_id' => 'required|string|max:64|regex:/^[A-Za-z0-9]+$/',
            'assistant_id' => 'required|integer'
        ]);

        $assistant = GptAssistant::findOrFail($request->input('assistant_id'));

        // Only the current on-chain owner of the Flovatar, with chat editing unlocked, can edit its persona.
        $address = $this->authenticatedFlowAddress();
        if(!$address || !$this->canEditAssistant($address, $assistant->flow_id)){
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $assistantApi = OpenAI::assistants()->modify($assistant->gpt_id,[
            'instructions' => $assistant->system_instruction.' '.$request->input('user_instructions')
        ]);

        $assistant->update([
            'description' => $request->input('description'),
            'user_instruction' => $request->input('user_instructions'),
            'metadata' => $assistantApi->toArray(),
            'voice_id'  => $request->input('voice_id')
        ]);

        return $assistant;
    }


    /**
     * Identity used for chat threads: the verified wallet address when logged in,
     * otherwise the anonymous numeric guest id generated by the chat page.
     * Unauthenticated callers cannot act as a wallet address.
     */
    protected function chatIdentity(Request $request){
        $address = $this->authenticatedFlowAddress();
        if($address){
            return $address;
        }

        $guestId = (string) ($request->input('guest_id') ?? $request->input('address'));
        return preg_match('/^[0-9]{1,12}$/', $guestId) ? $guestId : null;
    }

    /**
     * Checks on chain that $address owns the Flovatar and has unlocked chat editing (bio.chat == "true").
     */
    protected function canEditAssistant($address, $flovatarId){
        try {
            $results = Flow::script('./scripts/get_flovatar.cdc')->argAddress($address)->argInt($flovatarId)->run();
        } catch (ProcessFailedException $e) {
            info('Failed checking Flovatar ownership for chat update');
            return false;
        }

        $data = json_decode($results, true);
        if(!$data || ($data['type'] ?? null) !== 'Optional' || empty($data['value']['value']['fields'])){
            return false;
        }

        foreach($data['value']['value']['fields'] as $field){
            if(($field['name'] ?? null) === 'bio' && isset($field['value']['value']) && is_array($field['value']['value'])){
                foreach($field['value']['value'] as $entry){
                    if(($entry['key']['value'] ?? null) === 'chat' && ($entry['value']['value'] ?? null) === 'true'){
                        return true;
                    }
                }
            }
        }

        return false;
    }

}
