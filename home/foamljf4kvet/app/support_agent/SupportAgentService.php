<?php
declare(strict_types=1);

require_once __DIR__ . '/SupportAgentSanitizer.php';
require_once __DIR__ . '/SupportAgentConfig.php';
require_once __DIR__ . '/SupportAgentGuard.php';
require_once __DIR__ . '/SupportAgentKnowledgeBase.php';
require_once __DIR__ . '/SupportAgentMemoryStore.php';
require_once __DIR__ . '/SupportAgentAiClient.php';

final class SupportAgentService {
    private SupportAgentGuard $guard;
    private SupportAgentKnowledgeBase $kb;
    private SupportAgentMemoryStore $memory;
    private SupportAgentAiClient $ai;

    public function __construct(?SupportAgentMemoryStore $memory = null, ?SupportAgentAiClient $ai = null) {
        $this->guard = new SupportAgentGuard();
        $this->kb = new SupportAgentKnowledgeBase();
        $this->memory = $memory ?? new SupportAgentMemoryStore();
        $this->ai = $ai ?? new SupportAgentAiClient();
    }

    public function handle(array $input): array {
        $message = SupportAgentSanitizer::redact(SupportAgentSanitizer::text($input['message'] ?? '', 1600));
        $conversationId = SupportAgentSanitizer::conversationId($input['conversation_id'] ?? null);
        $locale = SupportAgentSanitizer::locale($input['locale'] ?? 'vi');
        $pageContext = SupportAgentSanitizer::pageContext($input['page_context'] ?? []);
        $inspection = $this->guard->inspect($message);

        if (!$inspection['safe']) {
            $fallback = $this->kb->deflect((string)$inspection['reason']);
            $answer = $fallback['answer'];
            $this->memory->append($conversationId, $message, $answer);
            return $this->shape($answer, $conversationId, (string)$inspection['topic'], (bool)$fallback['escalation'], $fallback['help_links'], $locale);
        }

        $topic = (string)$inspection['topic'];
        $base = $this->kb->answer($topic, $message);
        $aiAnswer = $this->ai->answer($message, $topic, $this->memory->load($conversationId), $pageContext);
        $answer = $aiAnswer ?: $base['answer'];
        if (SupportAgentSanitizer::containsForbiddenLeak($answer)) {
            $base = $this->kb->deflect('restricted');
            $answer = $base['answer'];
        }
        $this->memory->append($conversationId, $message, $answer);
        return $this->shape($answer, $conversationId, $topic, (bool)$base['escalation'], $base['help_links'], $locale);
    }

    private function shape(string $answer, string $conversationId, string $topic, bool $escalation, array $helpLinks, string $locale): array {
        return [
            'answer' => SupportAgentSanitizer::redact(SupportAgentSanitizer::text($answer, 1400)),
            'conversation_id' => $conversationId,
            'safe_topic' => $topic,
            'escalation' => $escalation,
            'citations' => [],
            'help_links' => $helpLinks,
            'locale' => $locale,
        ];
    }
}
