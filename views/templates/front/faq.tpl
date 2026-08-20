{**
 * Product FAQ and Customer Questions
 *
 * The shop's FAQ page.
 *
 * Shared answers first, then a section per product. Same <details> markup as the
 * product block, for the same reason: the text is in the page whether or not any
 * script runs.
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
            {if $mf_groups|count > 1}
                <nav class="megfaq__toc" aria-label="{l s='On this page' mod='megfaq'}">
                    <ul>
                        {foreach from=$mf_groups item=group}
                            <li>
                                <a href="#megfaq-group-{$group.id_product|intval}">
                                    {$group.title|escape:'html':'UTF-8'}
                                    <span class="megfaq__toc-count">({$group.entries|count})</span>
                                </a>
                            </li>
                        {/foreach}
                    </ul>
                </nav>
            {/if}

            {foreach from=$mf_groups item=group name=groups}
                <section class="megfaq__group" id="megfaq-group-{$group.id_product|intval}">
                    <h2 class="megfaq__group-title">
                        {if $group.url}
                            <a href="{$group.url|escape:'html':'UTF-8'}">{$group.title|escape:'html':'UTF-8'}</a>
                        {else}
                            {$group.title|escape:'html':'UTF-8'}
                        {/if}
                    </h2>

                    <div class="megfaq__list">
                        {foreach from=$group.entries item=entry name=faq}
                            <details class="megfaq__item"
                                     id="megfaq-{$entry.id|intval}"
                                     {if $mf_open_first && $smarty.foreach.groups.first && $smarty.foreach.faq.first} open{/if}>
                                <summary class="megfaq__q">{$entry.question nofilter}</summary>
                                <div class="megfaq__a">{$entry.answer nofilter}</div>
                            </details>
                        {/foreach}
                    </div>
                </section>
            {/foreach}
        {/if}

    </div>
{/block}
