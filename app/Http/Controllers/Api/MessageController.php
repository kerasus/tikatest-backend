<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    use Filter, CommonCRUD;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:messages.view')->only(['index', 'show']);
        $this->middleware('permission:messages.create')->only(['store']);
        $this->middleware('permission:messages.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => ['subject'],
            'filterRelationIds' => [
                [
                    'requestKey' => 'sender_ids',
                    'relationName' => 'sender',
                ],
                [
                    'requestKey' => 'receiver_ids',
                    'relationName' => 'receiver',
                ],
            ],
            'filterDate' => ['sent_at', 'created_at'],
            'eagerLoads' => ['school', 'sender', 'receiver'],
        ];

        return $this->commonIndex($request, Message::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|exists:schools,id',
            'sender_id' => 'required|exists:users,id',
            'receiver_id' => 'required|exists:users,id',
            'subject' => 'nullable|string|max:255',
            'body' => 'required|string',
            'attachment' => 'nullable|string|max:255',
            'sent_at' => 'nullable|date',
        ]);

        $data = $request->all();
        if (!$request->filled('sent_at')) {
            $data['sent_at'] = now();
        }

        $message = Message::create($data);

        return $this->jsonResponseOk($message->load(['sender', 'receiver']));
    }

    public function show(int $id): JsonResponse
    {
        $message = Message::with(['school', 'sender', 'receiver'])->findOrFail($id);

        return $this->jsonResponseOk($message);
    }

    public function destroy(Message $message): JsonResponse
    {
        return $this->commonDestroy($message);
    }

    public function myMessages(Request $request): JsonResponse
    {
        $userId = auth()->id();

        $messages = Message::where('receiver_id', $userId)
            ->orWhere('sender_id', $userId)
            ->with(['sender', 'receiver'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return $this->jsonResponseOk($messages);
    }

    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'subject' => 'nullable|string|max:255',
            'body' => 'required|string',
            'attachment' => 'nullable|string|max:255',
        ]);

        $data = $request->all();
        $data['sender_id'] = auth()->id();
        $data['sent_at'] = now();

        $message = Message::create($data);

        return $this->jsonResponseOk($message->load(['sender', 'receiver']));
    }

    public function sentMessages(Request $request): JsonResponse
    {
        $messages = Message::where('sender_id', auth()->id())
            ->with(['sender', 'receiver'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return $this->jsonResponseOk($messages);
    }

    public function receivedMessages(Request $request): JsonResponse
    {
        $messages = Message::where('receiver_id', auth()->id())
            ->with(['sender', 'receiver'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return $this->jsonResponseOk($messages);
    }
}
