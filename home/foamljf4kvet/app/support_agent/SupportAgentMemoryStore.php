<?php
declare(strict_types=1);

final class SupportAgentMemoryStore {
    private string $dir;
    private int $ttlSeconds;
    private int $maxMessages;

    public function __construct(?string $dir = null, ?int $ttlSeconds = null, ?int $maxMessages = null) {
        $this->dir = $dir ?: (string)SupportAgentConfig::value('memory_path');
        $this->ttlSeconds = $ttlSeconds ?? (int)SupportAgentConfig::value('memory_ttl_seconds');
        $this->maxMessages = $maxMessages ?? (int)SupportAgentConfig::value('memory_message_limit');
        if (!is_dir($this->dir)) @mkdir($this->dir, 0700, true);
    }

    public function load(string $conversationId): array {
        $file = $this->file($conversationId);
        if (!is_file($file) || filemtime($file) < time() - $this->ttlSeconds) return [];
        $raw = @file_get_contents($file);
        $data = $raw ? json_decode($raw, true) : null;
        return is_array($data['messages'] ?? null) ? $data['messages'] : [];
    }

    public function append(string $conversationId, string $userMessage, string $assistantAnswer): void {
        if (!is_dir($this->dir) || !is_writable($this->dir)) return;
        $messages = $this->load($conversationId);
        $messages[] = ['role' => 'user', 'content' => SupportAgentSanitizer::redact($userMessage), 'ts' => time()];
        $messages[] = ['role' => 'assistant', 'content' => SupportAgentSanitizer::redact($assistantAnswer), 'ts' => time()];
        $messages = array_slice($messages, -$this->maxMessages);
        $payload = ['conversation_id_hash' => hash('sha256', $conversationId), 'messages' => $messages, 'updated_at' => time()];
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded !== false) @file_put_contents($this->file($conversationId), $encoded, LOCK_EX);
    }

    private function file(string $conversationId): string {
        return $this->dir . '/' . hash('sha256', $conversationId) . '.json';
    }
}
