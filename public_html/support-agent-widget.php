<?php
declare(strict_types=1);

$supportAgentWidgetVersion = htmlspecialchars((string)($assetVer ?? '20260505a'), ENT_QUOTES, 'UTF-8');
?>
<link rel="stylesheet" href="/assets/support-agent-widget.css?v=<?= $supportAgentWidgetVersion ?>">
<script src="/assets/support-agent-widget.js?v=<?= $supportAgentWidgetVersion ?>" defer></script>
