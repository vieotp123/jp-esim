<?php
declare(strict_types=1);

$supportAgentWidgetVersion = htmlspecialchars((string)($assetVer ?? '20260505a'), ENT_QUOTES, 'UTF-8');
if (!class_exists('SupportAgentConfig')) {
    $bootstrap = dirname(__DIR__) . '/home/foamljf4kvet/app/support_agent/SupportAgentBootstrap.php';
    if (is_file($bootstrap)) {
        require_once $bootstrap;
    }
}
if (class_exists('SupportAgentConfig') && !SupportAgentConfig::value('widget_enabled')) {
    return;
}
?>
<link rel="stylesheet" href="/assets/support-agent-widget.css?v=<?= $supportAgentWidgetVersion ?>">
<script src="/assets/support-agent-widget.js?v=<?= $supportAgentWidgetVersion ?>" defer></script>
