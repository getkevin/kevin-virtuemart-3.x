<style>
    .headline {
        margin-top: 10px;
        text-align: center;
    }

    .input-hidden {
        position: absolute;
        left: -9999px;
        visibility: hidden;
    }

    .bank-list-container {
        -webkit-appearance:none;
        -moz-appearance:none;
        appearance:none;

        margin: 0 5px 5px;
        padding: 0 10px 10px;
        display: flex;
        flex-wrap: wrap;
        align-content: center;
        justify-content: center;
        width: auto;
    }

    .bank-list-container input[type=radio]:checked + label {
        box-shadow: 0 0 0 1px black;
    }

    .bank-list-item {
        margin: 5px;
        width: 120px;
        padding: 5px;
        height:70px;
        line-height: 70px;

        cursor: pointer;
        transition: all 200ms ease-in;
    }

    .bank-list-item:hover {
        transform: scale(1.04);
    }

    .bank-logo {
        vertical-align: middle;
    }
</style>


<?php
defined('_JEXEC') or exit();

/**
 * Bank selection list in checkout template.
 *
 * @var array $viewData - template parameter array passed to layout renderer
 *
 * @since 1.0.0
 */
$headline = vmText::_('KEVIN_BANK_SELECT_HEADER');
$selectCountry = vmText::_('KEVIN_SELECT_COUNTRY');

// if selected country code is not on the list, do not select any country
$selectDefault = !(bool) $viewData['countries'][$viewData['selectedCountryCode']] ? 'selected' : '';

$html = "<div class='headline'>";
$html .= "<h5>$headline</h5>";
$html .= "<select name='country' id='country' onchange='updateCountryCode(this)'>";
$html .= "<option value='' disabled $selectDefault>$selectCountry</option>";
foreach ($viewData['countries'] as $countryCode => $countryName) {
    $selected = $viewData['selectedCountryCode'] === $countryCode ? 'selected' : '';

    $html .= "<option value='$countryCode' $selected>$countryName</option>";
}
$html .= '</select>';
$html .= '</div>';
$html .= "<button data-dynamic-update='1' id='refresh-bank-list' hidden></button>";

$html .= "<div class='bank-list-container'>";
foreach ($viewData['banks'] as $bank) {
    $checked = $bank['id'] === $viewData['selectedBankId'] ? "checked='checked'" : '';

    $html .= "<input type='radio' name='bank' id='{$bank['id']}' class='input-hidden' value='{$bank['id']}' $checked onclick='updateBankId(this)'/>";
    $html .= "<label for='{$bank['id']}' class='bank-list-item'>";
    $html .= "<img  class='bank-logo' src='{$bank['imageUri']}' alt='{$bank['id']}' />";
    $html .= '</label>';
}

if ($viewData['banks'] && $viewData['isCardEnabled']) {
    $checked = 'card' === $viewData['selectedBankId'] ? "checked='checked'" : '';

    $cardImageUri = dirname(__FILE__).'/../kevin/images/credit_card_stock.png';
    $image = 'data:image/png;base64,'.base64_encode(file_get_contents($cardImageUri));

    $html .= "<input type='radio' name='bank' id='card' class='input-hidden' value='card' $checked onclick='updateBankId(this)'/>";
    $html .= "<label for='card' class='bank-list-item'>";
    $html .= "<img  class='bank-logo' src='$image' alt='card' />";
    $html .= '</label>';
}
$html .= '</div>';

echo $html;
?>

<script>
    const updateUrl = window.location.href + 'index.php?option=com_virtuemart&view=vmplg&task=pluginNotification';

    function uncheckTos() {
        const tos = document.getElementById('tos');

        if (tos.checked) {
            tos.click();
        }
    }

    function updateCountryCode(selectObject) {
        uncheckTos();

        Virtuemart.startVmLoading({ data: {} }) //manually start loading animation

        const refreshBankListButton = document.getElementById('refresh-bank-list');
        const request = new XMLHttpRequest();

        request.onreadystatechange = ()  => {
            if (request.readyState === 4 && request.status === 200) {
                refreshBankListButton.click();
            } else if (request.readyState === 4) {
                alert('<?php echo vmText::_('KEVIN_BANK_LIST_LOADING_ERROR'); ?>');
                Virtuemart.stopVmLoading();
            }
        }

        const data = new FormData();
        data.append('selectedCountryCode', selectObject.value);

        request.open('POST', updateUrl, true);
        request.send(data);
    }

    function updateBankId(radioButtonObject) {
        uncheckTos();

        Virtuemart.startVmLoading({ data: {} }) //manually start loading animation

        const request = new XMLHttpRequest();

        request.onreadystatechange = ()  => {
            if (request.readyState === 4 && request.status === 200) {
                Virtuemart.stopVmLoading();
            } else if (request.readyState === 4) {
                alert('<?php echo vmText::_('KEVIN_BANK_LIST_SELECT_ERROR'); ?>');
                Virtuemart.stopVmLoading();
            }
        }

        const data = new FormData();
        data.append('selectedBankId', radioButtonObject.id);

        request.open('POST', updateUrl, true);
        request.send(data);
    }
</script>