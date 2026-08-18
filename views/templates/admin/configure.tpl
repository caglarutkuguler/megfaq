{**
 * Product FAQ and Customer Questions - back office.
 *
 * Three tabs: the questions, the settings, and a short page on how the thing is
 * meant to be used. The list and the editor share one screen on purpose - a
 * merchant answering a queue of questions should never have to navigate back to
 * find the next one.
 *
 * @author    MEG Venture <info@megventure.com>
 * @copyright 2019-2026 MEG Venture
 * @license   Academic Free License 3.0 (AFL-3.0)
 *}
<div class="mf-admin" data-mf-admin>

    {$mf_html nofilter}

    <div class="panel mf-header">
        <div class="mf-header__title">
            <h3 class="mf-h3">{$mf_module_name|escape:'html':'UTF-8'}</h3>
            <span class="mf-version">v{$mf_version|escape:'html':'UTF-8'}</span>
        </div>
        <div class="mf-stats">
            <div class="mf-stat">
                <span class="mf-stat__value">{$mf_published|intval}</span>
                <span class="mf-stat__label">{l s='Published' mod='megfaq'}</span>
            </div>
            <div class="mf-stat{if $mf_pending} mf-stat--alert{/if}">
                <span class="mf-stat__value">{$mf_pending|intval}</span>
                <span class="mf-stat__label">{l s='Waiting for an answer' mod='megfaq'}</span>
            </div>
            <div class="mf-stat">
                <span class="mf-stat__value">{$mf_globals|intval}</span>
                <span class="mf-stat__label">{l s='Shown on every product' mod='megfaq'}</span>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs mf-tabs">
        <li{if $mf_tab == 'list'} class="active"{/if}>
            <a href="#mf-tab-list" data-toggle="tab">
                <i class="icon icon-list"></i> {l s='Questions' mod='megfaq'}
                {if $mf_pending}<span class="badge">{$mf_pending|intval}</span>{/if}
            </a>
        </li>
        <li{if $mf_tab == 'settings'} class="active"{/if}>
            <a href="#mf-tab-settings" data-toggle="tab">
                <i class="icon icon-cogs"></i> {l s='Settings' mod='megfaq'}
            </a>
        </li>
        <li{if $mf_tab == 'help'} class="active"{/if}>
            <a href="#mf-tab-help" data-toggle="tab">
                <i class="icon icon-lightbulb-o"></i> {l s='How it works' mod='megfaq'}
            </a>
        </li>
    </ul>

    <div class="tab-content">

        {* ------------------------------------------------------------- list *}
        <div class="tab-pane{if $mf_tab == 'list'} active{/if}" id="mf-tab-list">

            <div class="panel">
                <h3 class="mf-h3">
                    {if $mf_edit}
                        {l s='Edit this entry' mod='megfaq'}
                    {else}
                        {l s='Add an entry' mod='megfaq'}
                    {/if}
                </h3>

                <form method="post" action="{$mf_form_url|escape:'html':'UTF-8'}" class="mf-entry-form">
                    <input type="hidden" name="mf_tab" value="list">
                    <input type="hidden" name="mf_id" value="{if $mf_edit}{$mf_edit.id|intval}{/if}">

                    {if $mf_edit && $mf_edit.customer_name}
                        <p class="mf-asked-by">
                            {l s='Asked by %s' sprintf=[$mf_edit.customer_name] mod='megfaq'}
                        </p>
                    {/if}

                    <div class="row">
                        <div class="col-lg-4">
                            <div class="form-group">
                                <label class="control-label">{l s='Shown on' mod='megfaq'}</label>
                                {if $mf_products}
                                    <select name="mf_id_product" class="form-control">
                                        <option value="0"{if !$mf_edit || !$mf_edit.id_product} selected{/if}>
                                            {l s='Every product - a shared answer' mod='megfaq'}
                                        </option>
                                        <optgroup label="{l s='One product only' mod='megfaq'}">
                                            {foreach from=$mf_products item=product}
                                                <option value="{$product.id|intval}"{if $mf_edit && $mf_edit.id_product == $product.id} selected{/if}>
                                                    {$product.name|escape:'html':'UTF-8'}{if $product.reference} ({$product.reference|escape:'html':'UTF-8'}){/if}
                                                </option>
                                            {/foreach}
                                            {* An entry whose product has since been deleted keeps its id
                                               rather than silently becoming a shared answer on save. *}
                                            {if $mf_edit && $mf_edit.id_product && !$mf_edit.product_found}
                                                <option value="{$mf_edit.id_product|intval}" selected>
                                                    {l s='Deleted product (#%d)' sprintf=[$mf_edit.id_product] mod='megfaq'}
                                                </option>
                                            {/if}
                                        </optgroup>
                                    </select>
                                    <p class="help-block">
                                        {l s='A shared answer appears on every product page and in the shop section of the FAQ page.' mod='megfaq'}
                                    </p>
                                {else}
                                    <input type="number" min="0" class="form-control" name="mf_id_product"
                                           value="{if $mf_edit}{$mf_edit.id_product|intval}{else}0{/if}">
                                    <p class="help-block">
                                        {l s='A product id, or 0 for a shared answer shown on every product page. Your catalogue is too large for a product list here, so the id has to be typed.' mod='megfaq'}
                                    </p>
                                {/if}
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="form-group">
                                <label class="control-label">{l s='Order' mod='megfaq'}</label>
                                <input type="number" min="0" class="form-control" name="mf_position"
                                       value="{if $mf_edit}{$mf_edit.position|intval}{else}0{/if}">
                                <p class="help-block">{l s='Lower numbers come first.' mod='megfaq'}</p>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="form-group">
                                <label class="control-label">{l s='Published' mod='megfaq'}</label>
                                <span class="switch prestashop-switch fixed-width-lg">
                                    <input type="radio" name="mf_active" id="mf_active_on" value="1"{if $mf_edit && $mf_edit.active} checked{/if}>
                                    <label for="mf_active_on">{l s='Yes' mod='megfaq'}</label>
                                    <input type="radio" name="mf_active" id="mf_active_off" value="0"{if !$mf_edit || !$mf_edit.active} checked{/if}>
                                    <label for="mf_active_off">{l s='No' mod='megfaq'}</label>
                                    <a class="slide-button btn"></a>
                                </span>
                            </div>
                        </div>
                    </div>

                    <ul class="nav nav-pills mf-lang-tabs">
                        {foreach from=$mf_languages item=language name=langs}
                            <li{if $smarty.foreach.langs.first} class="active"{/if}>
                                <a href="#mf-lang-{$language.id_lang|intval}" data-mf-lang>
                                    {$language.iso_code|escape:'html':'UTF-8'}
                                    {if $language.is_default}<span class="mf-lang-default">&#9679;</span>{/if}
                                </a>
                            </li>
                        {/foreach}
                    </ul>

                    <div class="mf-lang-panes">
                        {foreach from=$mf_languages item=language name=langs}
                            <div class="mf-lang-pane{if $smarty.foreach.langs.first} mf-lang-pane--active{/if}"
                                 id="mf-lang-{$language.id_lang|intval}">
                                <div class="form-group">
                                    <label class="control-label">
                                        {l s='Question' mod='megfaq'} - {$language.name|escape:'html':'UTF-8'}
                                    </label>
                                    <input type="text" class="form-control" maxlength="500"
                                           name="mf_question[{$language.id_lang|intval}]"
                                           value="{if $mf_edit}{$mf_edit.text[$language.id_lang].question|escape:'html':'UTF-8'}{/if}">
                                </div>
                                <div class="form-group">
                                    <label class="control-label">
                                        {l s='Answer' mod='megfaq'} - {$language.name|escape:'html':'UTF-8'}
                                    </label>
                                    <textarea class="form-control" rows="5"
                                              name="mf_answer[{$language.id_lang|intval}]">{if $mf_edit}{$mf_edit.text[$language.id_lang].answer|escape:'html':'UTF-8'}{/if}</textarea>
                                    <p class="help-block">
                                        {l s='A language with no answer is simply not shown in that language. Nothing is machine-translated for you.' mod='megfaq'}
                                    </p>
                                </div>
                            </div>
                        {/foreach}
                    </div>

                    <div class="mf-entry-actions">
                        <button type="submit" name="submitMegFaqEntry" class="btn btn-primary">
                            {if $mf_edit}{l s='Save entry' mod='megfaq'}{else}{l s='Add entry' mod='megfaq'}{/if}
                        </button>
                        {if $mf_edit}
                            <a class="btn btn-default" href="{$mf_form_url|escape:'html':'UTF-8'}">
                                {l s='Cancel' mod='megfaq'}
                            </a>
                        {/if}
                    </div>
                </form>
            </div>

            <div class="panel">
                <form method="get" action="{$mf_form_url|escape:'html':'UTF-8'}" class="mf-filters">
                    <input type="hidden" name="configure" value="megfaq">
                    <input type="hidden" name="mf_tab" value="list">

                    <select name="mf_status" class="form-control mf-filter">
                        <option value="">{l s='Any status' mod='megfaq'}</option>
                        <option value="pending"{if $mf_filters.status == 'pending'} selected{/if}>
                            {l s='Waiting for an answer' mod='megfaq'}
                        </option>
                        <option value="published"{if $mf_filters.status == 'published'} selected{/if}>
                            {l s='Published' mod='megfaq'}
                        </option>
                    </select>

                    <select name="mf_scope" class="form-control mf-filter">
                        <option value="">{l s='Anywhere' mod='megfaq'}</option>
                        <option value="global"{if $mf_filters.scope == 'global'} selected{/if}>
                            {l s='Every product' mod='megfaq'}
                        </option>
                        <option value="product"{if $mf_filters.scope == 'product'} selected{/if}>
                            {l s='One product' mod='megfaq'}
                        </option>
                    </select>

                    <input type="search" name="mf_search" class="form-control mf-filter mf-filter--search"
                           placeholder="{l s='Search questions and answers' mod='megfaq'}"
                           value="{$mf_filters.search|escape:'html':'UTF-8'}">

                    <button type="submit" class="btn btn-default">{l s='Filter' mod='megfaq'}</button>
                    <a class="btn btn-link" href="{$mf_form_url|escape:'html':'UTF-8'}">{l s='Clear' mod='megfaq'}</a>
                </form>

                {if !$mf_rows}
                    <p class="mf-empty">
                        {l s='Nothing here yet. Add your first entry above, or wait for a customer to ask something.' mod='megfaq'}
                    </p>
                {else}
                    <table class="table mf-list">
                        <thead>
                            <tr>
                                <th>{l s='Question' mod='megfaq'}</th>
                                <th>{l s='Shown on' mod='megfaq'}</th>
                                <th>{l s='Status' mod='megfaq'}</th>
                                <th>{l s='Added' mod='megfaq'}</th>
                                <th class="mf-list__actions">{l s='Actions' mod='megfaq'}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach from=$mf_rows item=row}
                                <tr>
                                    <td>
                                        <strong>{$row.question|escape:'html':'UTF-8'}</strong>
                                        {if $row.from_customer}
                                            <span class="mf-badge mf-badge--asked">
                                                {l s='asked by %s' sprintf=[$row.asked_by] mod='megfaq'}
                                            </span>
                                        {/if}
                                        {if !$row.answered}
                                            <span class="mf-badge mf-badge--warn">{l s='no answer yet' mod='megfaq'}</span>
                                        {/if}
                                    </td>
                                    <td>
                                        {if $row.is_global}
                                            <span class="mf-badge mf-badge--shared">{l s='Every product' mod='megfaq'}</span>
                                        {else}
                                            {$row.product|escape:'html':'UTF-8'}
                                        {/if}
                                    </td>
                                    <td>
                                        {if $row.active}
                                            <span class="mf-status mf-status--on">{l s='Published' mod='megfaq'}</span>
                                        {else}
                                            <span class="mf-status mf-status--off">{l s='Not published' mod='megfaq'}</span>
                                        {/if}
                                    </td>
                                    <td class="mf-list__date">{$row.date_add|escape:'html':'UTF-8'}</td>
                                    <td class="mf-list__actions">
                                        <a class="btn btn-default btn-sm" href="{$row.edit_url|escape:'html':'UTF-8'}">
                                            <i class="icon icon-pencil"></i> {l s='Edit' mod='megfaq'}
                                        </a>
                                        <form method="post" action="{$mf_form_url|escape:'html':'UTF-8'}" class="mf-inline">
                                            <input type="hidden" name="mf_tab" value="list">
                                            <input type="hidden" name="mf_id" value="{$row.id|intval}">
                                            <button type="submit" name="submitMegFaqToggle" class="btn btn-default btn-sm">
                                                {if $row.active}{l s='Unpublish' mod='megfaq'}{else}{l s='Publish' mod='megfaq'}{/if}
                                            </button>
                                        </form>
                                        <form method="post" action="{$mf_form_url|escape:'html':'UTF-8'}" class="mf-inline">
                                            <input type="hidden" name="mf_tab" value="list">
                                            <input type="hidden" name="mf_id" value="{$row.id|intval}">
                                            <button type="submit" name="submitMegFaqDelete" class="btn btn-default btn-sm mf-danger"
                                                    data-mf-confirm="{l s='Delete this entry for good?' mod='megfaq'}">
                                                <i class="icon icon-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>

                    {if $mf_pages > 1}
                        <ul class="pagination mf-pager">
                            {section name=p start=1 loop=$mf_pages+1 step=1}
                                <li{if $smarty.section.p.index == $mf_page} class="active"{/if}>
                                    <a href="{$mf_form_url|escape:'html':'UTF-8'}&amp;mf_tab=list&amp;mf_page={$smarty.section.p.index|intval}&amp;mf_status={$mf_filters.status|escape:'url'}&amp;mf_scope={$mf_filters.scope|escape:'url'}&amp;mf_search={$mf_filters.search|escape:'url'}">
                                        {$smarty.section.p.index|intval}
                                    </a>
                                </li>
                            {/section}
                        </ul>
                    {/if}

                    <p class="mf-count">
                        {l s='%d entries' sprintf=[$mf_total] mod='megfaq'}
                    </p>
                {/if}
            </div>
        </div>

        {* --------------------------------------------------------- settings *}
        <div class="tab-pane{if $mf_tab == 'settings'} active{/if}" id="mf-tab-settings">
            <div class="panel">
                <form method="post" action="{$mf_form_url|escape:'html':'UTF-8'}">
                    <input type="hidden" name="mf_tab" value="settings">

                    <h3 class="mf-h3">{l s='Where it shows' mod='megfaq'}</h3>

                    <div class="form-group">
                        <label class="control-label">{l s='Show the FAQ block on product pages' mod='megfaq'}</label>
                        <span class="switch prestashop-switch fixed-width-lg">
                            <input type="radio" name="MEGFAQ_ON_PRODUCT" id="mf_onprod_on" value="1"{if $mf_settings.MEGFAQ_ON_PRODUCT} checked{/if}>
                            <label for="mf_onprod_on">{l s='Yes' mod='megfaq'}</label>
                            <input type="radio" name="MEGFAQ_ON_PRODUCT" id="mf_onprod_off" value="0"{if !$mf_settings.MEGFAQ_ON_PRODUCT} checked{/if}>
                            <label for="mf_onprod_off">{l s='No' mod='megfaq'}</label>
                            <a class="slide-button btn"></a>
                        </span>
                    </div>

                    <div class="form-group">
                        <label class="control-label">{l s='Include the shared answers on product pages' mod='megfaq'}</label>
                        <span class="switch prestashop-switch fixed-width-lg">
                            <input type="radio" name="MEGFAQ_GLOBAL_ON_PRODUCT" id="mf_glob_on" value="1"{if $mf_settings.MEGFAQ_GLOBAL_ON_PRODUCT} checked{/if}>
                            <label for="mf_glob_on">{l s='Yes' mod='megfaq'}</label>
                            <input type="radio" name="MEGFAQ_GLOBAL_ON_PRODUCT" id="mf_glob_off" value="0"{if !$mf_settings.MEGFAQ_GLOBAL_ON_PRODUCT} checked{/if}>
                            <label for="mf_glob_off">{l s='No' mod='megfaq'}</label>
                            <a class="slide-button btn"></a>
                        </span>
                        <p class="help-block">
                            {l s='Entries set to every product appear under the ones written for that product.' mod='megfaq'}
                        </p>
                    </div>

                    <div class="form-group">
                        <label class="control-label">{l s='Publish the shop FAQ page' mod='megfaq'}</label>
                        <span class="switch prestashop-switch fixed-width-lg">
                            <input type="radio" name="MEGFAQ_PAGE" id="mf_page_on" value="1"{if $mf_settings.MEGFAQ_PAGE} checked{/if}>
                            <label for="mf_page_on">{l s='Yes' mod='megfaq'}</label>
                            <input type="radio" name="MEGFAQ_PAGE" id="mf_page_off" value="0"{if !$mf_settings.MEGFAQ_PAGE} checked{/if}>
                            <label for="mf_page_off">{l s='No' mod='megfaq'}</label>
                            <a class="slide-button btn"></a>
                        </span>
                        {if $mf_page_short_url}
                            <p class="help-block mf-short-url">
                                {l s='The short address %s works too: it sends each visitor to the page for the language they are browsing in.' sprintf=[$mf_page_short_url] mod='megfaq'}
                            </p>
                        {/if}
                        <p class="help-block">
                            {l s='The page exists once per language, at the address that language uses:' mod='megfaq'}
                        </p>
                        <ul class="mf-page-urls">
                            {foreach from=$mf_page_urls item=page}
                                <li>
                                    <span class="mf-page-urls__iso">{$page.iso_code|escape:'html':'UTF-8'}</span>
                                    <a href="{$page.url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{$page.url|escape:'html':'UTF-8'}</a>
                                </li>
                            {/foreach}
                        </ul>
                    </div>

                    <div class="form-group">
                        <label class="control-label">{l s='Open the first question by default' mod='megfaq'}</label>
                        <span class="switch prestashop-switch fixed-width-lg">
                            <input type="radio" name="MEGFAQ_OPEN_FIRST" id="mf_open_on" value="1"{if $mf_settings.MEGFAQ_OPEN_FIRST} checked{/if}>
                            <label for="mf_open_on">{l s='Yes' mod='megfaq'}</label>
                            <input type="radio" name="MEGFAQ_OPEN_FIRST" id="mf_open_off" value="0"{if !$mf_settings.MEGFAQ_OPEN_FIRST} checked{/if}>
                            <label for="mf_open_off">{l s='No' mod='megfaq'}</label>
                            <a class="slide-button btn"></a>
                        </span>
                    </div>

                    <div class="form-group">
                        <label class="control-label">{l s='Show untranslated entries in the shop default language' mod='megfaq'}</label>
                        <span class="switch prestashop-switch fixed-width-lg">
                            <input type="radio" name="MEGFAQ_FALLBACK" id="mf_fb_on" value="1"{if $mf_settings.MEGFAQ_FALLBACK} checked{/if}>
                            <label for="mf_fb_on">{l s='Yes' mod='megfaq'}</label>
                            <input type="radio" name="MEGFAQ_FALLBACK" id="mf_fb_off" value="0"{if !$mf_settings.MEGFAQ_FALLBACK} checked{/if}>
                            <label for="mf_fb_off">{l s='No' mod='megfaq'}</label>
                            <a class="slide-button btn"></a>
                        </span>
                        <p class="help-block">
                            {l s='An entry you have not translated yet is shown in your default language instead of being hidden. Question and answer always come from the same language - half a translation is worse than none. Switch this off to hide untranslated entries completely.' mod='megfaq'}
                        </p>
                    </div>

                    <h3 class="mf-h3">{l s='Questions from shoppers' mod='megfaq'}</h3>

                    <div class="form-group">
                        <label class="control-label">{l s='Let people ask a question' mod='megfaq'}</label>
                        <span class="switch prestashop-switch fixed-width-lg">
                            <input type="radio" name="MEGFAQ_ASK" id="mf_ask_on" value="1"{if $mf_settings.MEGFAQ_ASK} checked{/if}>
                            <label for="mf_ask_on">{l s='Yes' mod='megfaq'}</label>
                            <input type="radio" name="MEGFAQ_ASK" id="mf_ask_off" value="0"{if !$mf_settings.MEGFAQ_ASK} checked{/if}>
                            <label for="mf_ask_off">{l s='No' mod='megfaq'}</label>
                            <a class="slide-button btn"></a>
                        </span>
                        <p class="help-block">
                            {l s='A question is never published on its own. It waits until you write the answer.' mod='megfaq'}
                        </p>
                    </div>

                    <div class="form-group">
                        <label class="control-label">{l s='Who may ask' mod='megfaq'}</label>
                        <select name="MEGFAQ_ASK_WHO" class="form-control fixed-width-xl">
                            {foreach from=$mf_who_choices key=value item=label}
                                <option value="{$value|escape:'html':'UTF-8'}"{if $mf_settings.MEGFAQ_ASK_WHO == $value} selected{/if}>
                                    {$label|escape:'html':'UTF-8'}
                                </option>
                            {/foreach}
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="control-label">{l s='Questions allowed per hour, per e-mail address' mod='megfaq'}</label>
                        <input type="number" min="0" class="form-control fixed-width-sm"
                               name="MEGFAQ_FLOOD" value="{$mf_settings.MEGFAQ_FLOOD|intval}">
                        <p class="help-block">{l s='0 turns the limit off.' mod='megfaq'}</p>
                    </div>

                    <div class="form-group">
                        <label class="control-label">{l s='Consent page to link on the form' mod='megfaq'}</label>
                        <select name="MEGFAQ_CONSENT_CMS" class="form-control fixed-width-xl">
                            <option value="0">{l s='No consent checkbox' mod='megfaq'}</option>
                            {foreach from=$mf_cms_pages item=page}
                                <option value="{$page.id|intval}"{if $mf_settings.MEGFAQ_CONSENT_CMS == $page.id} selected{/if}>
                                    {$page.name|escape:'html':'UTF-8'}
                                </option>
                            {/foreach}
                        </select>
                    </div>

                    <h3 class="mf-h3">{l s='E-mail' mod='megfaq'}</h3>

                    <div class="form-group">
                        <label class="control-label">{l s='Tell me when a question arrives' mod='megfaq'}</label>
                        <span class="switch prestashop-switch fixed-width-lg">
                            <input type="radio" name="MEGFAQ_NOTIFY" id="mf_notify_on" value="1"{if $mf_settings.MEGFAQ_NOTIFY} checked{/if}>
                            <label for="mf_notify_on">{l s='Yes' mod='megfaq'}</label>
                            <input type="radio" name="MEGFAQ_NOTIFY" id="mf_notify_off" value="0"{if !$mf_settings.MEGFAQ_NOTIFY} checked{/if}>
                            <label for="mf_notify_off">{l s='No' mod='megfaq'}</label>
                            <a class="slide-button btn"></a>
                        </span>
                    </div>

                    <div class="form-group">
                        <label class="control-label">{l s='Send those to' mod='megfaq'}</label>
                        <input type="email" class="form-control fixed-width-xl"
                               name="MEGFAQ_NOTIFY_EMAIL" value="{$mf_settings.MEGFAQ_NOTIFY_EMAIL|escape:'html':'UTF-8'}">
                        <p class="help-block">{l s='Leave empty to use the shop e-mail address.' mod='megfaq'}</p>
                    </div>

                    <div class="form-group">
                        <label class="control-label">{l s='Tell the shopper when their question is answered' mod='megfaq'}</label>
                        <span class="switch prestashop-switch fixed-width-lg">
                            <input type="radio" name="MEGFAQ_ANSWER_MAIL" id="mf_ans_on" value="1"{if $mf_settings.MEGFAQ_ANSWER_MAIL} checked{/if}>
                            <label for="mf_ans_on">{l s='Yes' mod='megfaq'}</label>
                            <input type="radio" name="MEGFAQ_ANSWER_MAIL" id="mf_ans_off" value="0"{if !$mf_settings.MEGFAQ_ANSWER_MAIL} checked{/if}>
                            <label for="mf_ans_off">{l s='No' mod='megfaq'}</label>
                            <a class="slide-button btn"></a>
                        </span>
                        <p class="help-block">
                            {l s='Sent once, when the entry is published with an answer.' mod='megfaq'}
                        </p>
                    </div>

                    <h3 class="mf-h3">{l s='The Questions list in this back office' mod='megfaq'}</h3>

                    <div class="form-group">
                        <label class="control-label">{l s='Rows to show before paging' mod='megfaq'}</label>
                        <input type="number" min="5" max="100" class="form-control fixed-width-sm"
                               name="MEGFAQ_PER_PAGE" value="{$mf_settings.MEGFAQ_PER_PAGE|intval}">
                        <p class="help-block">
                            {l s='How many entries the Questions tab lists on one page, between 5 and 100. This is a back office setting only - it changes nothing your customers see.' mod='megfaq'}
                        </p>
                    </div>

                    <div class="panel-footer">
                        <button type="submit" name="submitMegFaqSettings" class="btn btn-default pull-right">
                            <i class="process-icon-save"></i> {l s='Save' mod='megfaq'}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {* ------------------------------------------------------------- help *}
        <div class="tab-pane{if $mf_tab == 'help'} active{/if}" id="mf-tab-help">
            <div class="panel">
                <h3 class="mf-h3">{l s='Answer once, show everywhere' mod='megfaq'}</h3>
                <p class="mf-lead">
                    {l s='Most FAQ modules make you write the same answer once per product. This one does not, and that is the whole idea.' mod='megfaq'}
                </p>

                <h4>{l s='1. Write the answers everyone needs' mod='megfaq'}</h4>
                <p>
                    {l s='Set "Shown on" to 0 and the entry appears on every product page. Delivery times, licence terms, what happens after an order - write each one once. This is the part that pays for itself.' mod='megfaq'}
                </p>

                <h4>{l s='2. Add the ones that belong to a single product' mod='megfaq'}</h4>
                <p>
                    {l s='Put the product id in "Shown on" and the entry appears only there, above the shared answers.' mod='megfaq'}
                </p>

                <h4>{l s='3. Let the questions come to you' mod='megfaq'}</h4>
                <p>
                    {l s='A shopper who asks is telling you what your product page failed to say. Their question lands here unpublished; write the answer, publish it, and it becomes a FAQ entry the next person reads instead of asking.' mod='megfaq'}
                </p>

                <h4>{l s='4. Translate at your own pace' mod='megfaq'}</h4>
                <p>
                    {l s='Each entry holds one question and answer per language. A language you have not filled in simply does not show the entry - nobody is ever served a question in a language they did not choose.' mod='megfaq'}
                </p>

                <h3 class="mf-h3">{l s='About FAQ rich results' mod='megfaq'}</h3>
                <p>
                    {l s='This module does not add FAQPage structured data, and that is on purpose. Google stopped showing FAQ rich results on 7 May 2026 and removed the documentation for them in June. Adding the markup would not bring the boxes back. What still works is what this module does: putting the questions and answers in the page the server sends, where shoppers read them and where search engines and AI answer engines can quote them.' mod='megfaq'}
                </p>
            </div>
        </div>

    </div>
</div>
