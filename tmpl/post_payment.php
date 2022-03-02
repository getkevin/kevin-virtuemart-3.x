<?php

defined('_JEXEC') or exit();

/**
 * Post payment screen template.
 *
 * @var array $viewData - template parameter array passed to layout renderer
 *
 * @since 1.0.0
 */
$message = $viewData['message'] ? "<h4>{$viewData['message']}</h4>" : '';
$buttonText = $viewData['buttonText'];
$buttonLink = $viewData['buttonLink'];
$notificationDisplayStyle = $viewData['notificationBubbleStyle'];

$html = '<div class="post_payment_order_total" style="width: 100%">';
$html .= $message;
$html .= '</div>';

if ($buttonLink && $buttonText) {
    $html .= "<a class='vm-button-correct' href='$buttonLink'>$buttonText</a>";
}

echo $html;
