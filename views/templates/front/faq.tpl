{**
 * Product FAQ and Customer Questions
 *
 * The shop's FAQ page.
 *
 * A search box, then the shared answers, then one collapsed row per product.
 * Everything is in the markup from the first byte: the rows are <details>
 * elements, the search is a GET form the server answers on its own, and the
 * script in front.js only makes the same things happen without a round trip.
 *
 * `hidden` and `open` on a row are the server's answer to ?q=. data-open is
 * what the row looks like with no search at all, kept for the script so that
 * clearing the box on a page that arrived filtered restores the page rather
 * than the filter.
 *
 * @author    MEG Venture <info@megventure.com>
 * @copyright 2019-2026 MEG Venture & Consulting Ltd.
 * @license   https://opensource.org/licenses/MIT MIT License
 *}
{extends file='page.tpl'}

{block name='page_title'}
    {l s='Frequently asked questions' mod='megfaq'}
{/block}

{block name='page_content'}
    <div class="megfaq megfaq--page">

        {if !$mf_groups}
            <p class="megfaq__empty">
                {l s='There are no published questions yet.' mod='megfaq'}
            </p>
        {else}
            <form class="megfaq__search" role="search" method="get"
                  action="{$mf_page_url|escape:'html':'UTF-8'}" data-megfaq-search>
                <label class="megfaq__search-label" for="megfaq-q">
                    {l s='Search the questions and answers' mod='megfaq'}
                </label>
                <div class="megfaq__search-box">
                    <svg class="megfaq__search-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <circle cx="10.5" cy="10.5" r="6.5" fill="none" stroke="currentColor" stroke-width="2"/>
                        <path d="M15.5 15.5 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <input type="search" class="megfaq__search-input" id="megfaq-q" name="q"
                           value="{$mf_query|escape:'html':'UTF-8'}"
                           placeholder="{l s='Type a word, a product name or a question' mod='megfaq'}"
                           maxlength="100" autocomplete="off" autocapitalize="off" spellcheck="false">
                    <button type="button" class="megfaq__search-clear"
                            aria-label="{l s='Clear the search' mod='megfaq'}"{if $mf_query === ''} hidden{/if}>
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M6 6l12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>
                <p class="megfaq__search-status" role="status" aria-live="polite" data-megfaq-status
                   data-one="{l s='1 result' mod='megfaq'}"
                   data-many="{l s='%d results' mod='megfaq'}"
                   data-none="{l s='No results. Try a different word, or clear the search to see every question.' mod='megfaq'}">
                    {if $mf_searching}
                        {if $mf_matches == 0}
                            {l s='No results. Try a different word, or clear the search to see every question.' mod='megfaq'}
                        {elseif $mf_matches == 1}
                            {l s='1 result' mod='megfaq'}
                        {else}
                            {l s='%d results' sprintf=[$mf_matches] mod='megfaq'}
                        {/if}
                    {/if}
                </p>
            </form>

            {if $mf_groups|count == 1}
                {* One group only - nothing to collapse, just the list in a frame. *}
                {foreach from=$mf_groups item=group}
                    <div class="megfaq__list megfaq__list--flat"{if $mf_matches == 0} hidden{/if}>
                        {foreach from=$group.entries item=entry}
                            <details class="megfaq__item" id="megfaq-{$entry.id|intval}"{if $entry.open} open{/if}{if !$entry.match} hidden{/if}{if $entry.default_open} data-open="1"{/if}>
                                <summary class="megfaq__q">{$entry.question nofilter}</summary>
                                <div class="megfaq__a">{$entry.answer nofilter}</div>
                            </details>
                        {/foreach}
                    </div>
                    {if $group.url}
                        <p class="megfaq__group-link">
                            <a href="{$group.url|escape:'html':'UTF-8'}">{l s='Go to the product page' mod='megfaq'}</a>
                        </p>
                    {/if}
                {/foreach}
            {else}
                <div class="megfaq__groups" data-megfaq-groups{if $mf_matches == 0} hidden{/if}>
                    {foreach from=$mf_groups item=group}
                        <details class="megfaq__group" id="megfaq-group-{$group.id_product|intval}"{if $group.open} open{/if}{if !$group.match} hidden{/if}{if $group.default_open} data-open="1"{/if}>
                            <summary class="megfaq__group-title">
                                <span class="megfaq__group-name">{$group.title|escape:'html':'UTF-8'}</span>
                                <span class="megfaq__group-count" data-megfaq-count="{$group.entries|count}">{$group.matches|intval}</span>
                            </summary>
                            <div class="megfaq__group-body">
                                <div class="megfaq__list">
                                    {foreach from=$group.entries item=entry}
                                        <details class="megfaq__item" id="megfaq-{$entry.id|intval}"{if $entry.open} open{/if}{if !$entry.match} hidden{/if}{if $entry.default_open} data-open="1"{/if}>
                                            <summary class="megfaq__q">{$entry.question nofilter}</summary>
                                            <div class="megfaq__a">{$entry.answer nofilter}</div>
                                        </details>
                                    {/foreach}
                                </div>
                                {if $group.url}
                                    <p class="megfaq__group-link">
                                        <a href="{$group.url|escape:'html':'UTF-8'}">{l s='Go to the product page' mod='megfaq'}</a>
                                    </p>
                                {/if}
                            </div>
                        </details>
                    {/foreach}
                </div>
            {/if}
        {/if}

    </div>
{/block}
