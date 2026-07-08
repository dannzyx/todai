<?php

namespace App\Http\Controllers;

use App\Ai\Agents\ChatAgent;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    /**
     * Session key holding the current chat conversation id.
     */
    protected const SESSION_KEY = 'todai.chat_conversation';

    /**
     * Show the chat page with the ongoing conversation.
     */
    public function index(Request $request): Response
    {
        $conversationId = $request->session()->get(self::SESSION_KEY);

        return Inertia::render('Chat', [
            'messages' => $conversationId ? $this->transcript($conversationId) : [],
            'createdTasks' => $request->session()->get('chatCreatedTasks', []),
        ]);
    }

    /**
     * Send a message to Todai, which may create tasks, then reply.
     */
    public function send(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $user = $request->user();
        $agent = new ChatAgent($user);

        $conversationId = $request->session()->get(self::SESSION_KEY);

        $agent = $conversationId
            ? $agent->continue($conversationId, as: $user)
            : $agent->forUser($user);

        $response = $agent->prompt($validated['message']);

        $request->session()->put(self::SESSION_KEY, $response->conversationId);

        return to_route('chat.index')->with('chatCreatedTasks', $this->summarise($agent->createdTasks));
    }

    /**
     * Start a fresh conversation.
     */
    public function reset(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return to_route('chat.index');
    }

    /**
     * Load the user/assistant transcript for a conversation.
     *
     * @return array<int, array{id: string, role: string, content: string}>
     */
    protected function transcript(string $conversationId): array
    {
        $table = config('ai.conversations.tables.messages', 'agent_conversation_messages');

        return DB::table($table)
            ->where('conversation_id', $conversationId)
            ->whereIn('role', ['user', 'assistant'])
            ->where('content', '!=', '')
            ->orderBy('created_at')
            ->get(['id', 'role', 'content'])
            ->map(fn (object $row): array => [
                'id' => (string) $row->id,
                'role' => (string) $row->role,
                'content' => (string) $row->content,
            ])
            ->all();
    }

    /**
     * Reduce a collection of created tasks to a display-friendly payload.
     *
     * @param  Collection<int, Task>  $tasks
     * @return array<int, array{id: string, title: string, due_date: ?string, project: ?string}>
     */
    protected function summarise($tasks): array
    {
        return $tasks
            ->map(fn (Task $task): array => [
                'id' => $task->id,
                'title' => $task->title,
                'due_date' => $task->due_date?->toDateString(),
                'project' => $task->project?->name,
            ])
            ->all();
    }
}
