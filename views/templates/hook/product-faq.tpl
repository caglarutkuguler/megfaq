{**
 * Product FAQ and Customer Questions
 *
 * The FAQ block under a product.
 *
 * Every question and answer is in the markup from the first byte. The accordion
 * is a <details> element, so the text is present, selectable and findable with
 * the browser's own search whether or not any JavaScript runs - and a crawler or
 * an answer engine reading the served HTML gets the whole thing.
 *
 * No FAQPage structured data, deliberately: Google stopped showing FAQ rich
 * results in May 2026. The content is the point; the markup was only ever a way
 * of asking for a box that no longer exists.
 *
 * @author    MEG Venture <info@megventure.com>
 * @copyright 2019-2026 MEG Venture
 * @license   Academic Free License 3.0 (AFL-3.0)
 *}
<section class="megfaq" id="megfaq">

    {if $mf_notice}
        <p class="megfaq__notice" role="status">{$mf_notice|escape:'html':'UTF-8'}</p>
    {/if}

    {if $mf_entries}
        <h2 class="megfaq__heading">{l s='Questions and answers' mod='megfaq'}</h2>

        <div class="megfaq__list">
            {foreach from=$mf_entries item=entry name=faq}
                <details class="megfaq__item{if $entry.is_global} megfaq__item--shared{/if}"
                         id="megfaq-{$entry.id|intval}"
                         {if $mf_open_first && $smarty.foreach.faq.first} open{/if}>
                    <summary class="megfaq__q">{$entry.question nofilter}</summary>
                    <div class="megfaq__a">{$entry.answer nofilter}</div>
                </details>
            {/foreach}
        </div>
    {/if}

    {if $mf_can_ask}
        <div class="megfaq__ask">
            <h3 class="megfaq__ask-heading">
                {if $mf_entries}
                    {l s='Still not sure? Ask us' mod='megfaq'}
                {else}
                    {l s='Have a question about this product?' mod='megfaq'}
                {/if}
            </h3>
            <p class="megfaq__ask-lead">
                {l s='We answer here on this page, so the next person with your question finds it too.' mod='megfaq'}
            </p>

            <form method="post" action="{$mf_ask_url|escape:'html':'UTF-8'}" class="megfaq__form">
                <input type="hidden" name="id_product" value="{$mf_id_product|intval}">

                {* Not display:none - some bots skip hidden inputs. Off-screen and
                   out of the tab order is invisible to a person and present to a
                   script. *}
                <p class="megfaq__hp" aria-hidden="true">
                    <label for="mf_website">{l s='Leave this field empty' mod='megfaq'}</label>
                    <input type="text" id="mf_website" name="mf_website" value="" tabindex="-1" autocomplete="off">
                </p>

                <div class="megfaq__row">
                    <label for="mf_name">{l s='Your name' mod='megfaq'}</label>
                    <input type="text" id="mf_name" name="mf_name" required
                           maxlength="60" value="{$mf_prefill.name|escape:'html':'UTF-8'}">
                </div>

                <div class="megfaq__row">
                    <label for="mf_email">{l s='Your e-mail' mod='megfaq'}</label>
                    <input type="email" id="mf_email" name="mf_email" required
                           maxlength="128" value="{$mf_prefill.email|escape:'html':'UTF-8'}">
                    <small>{l s='Only used to let you know when we answer. It is never shown on the page.' mod='megfaq'}</small>
                </div>

                <div class="megfaq__row">
                    <label for="mf_question">{l s='Your question' mod='megfaq'}</label>
                    <textarea id="mf_question" name="mf_question" rows="3" required maxlength="500"></textarea>
                </div>

                {if $mf_consent}
                    <p class="megfaq__consent">
                        <label>
                            <input type="checkbox" name="mf_consent" value="1" required>
                            {l s='I have read and accept' mod='megfaq'}
                            <a href="{$mf_consent.url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">
                                {$mf_consent.text|escape:'html':'UTF-8'}
                            </a>
                        </label>
                    </p>
                {/if}

                <button type="submit" class="btn btn-primary megfaq__submit">
                    {l s='Send my question' mod='megfaq'}
                </button>
            </form>
        </div>
    {/if}

    {if $mf_page_url && $mf_entries}
        <p class="megfaq__all">
            <a href="{$mf_page_url|escape:'html':'UTF-8'}">{l s='See all questions and answers' mod='megfaq'}</a>
        </p>
    {/if}

</section>
