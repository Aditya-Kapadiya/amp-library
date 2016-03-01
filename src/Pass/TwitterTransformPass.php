<?php
/*
 * Copyright 2016 Google
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Lullabot\AMP\Pass;

use QueryPath\DOMQuery;

use Lullabot\AMP\ActionTakenLine;
use Lullabot\AMP\ActionTakenType;

/**
 * Class TwitterTransformPass
 * @package Lullabot\AMP\Pass
 */
class TwitterTransformPass extends BasePass
{
    function pass()
    {
        $all_tweets = $this->q->top()->find('blockquote[class="twitter-tweet"]');
        /** @var DOMQuery $el */
        foreach ($all_tweets as $el) {
            /** @var \DOMElement $dom_el */
            $dom_el = $el->get(0);
            $lineno = $dom_el->getLineNo();
            $tweet_id = $this->getTweetId($el);
            $context_string = $this->getContextString($dom_el);

            // Get reference to associated <script> tag, if any.
            $twitter_script_tag = $this->getTwitterScriptTag($el);
            $tweet_attributes = $this->getTweetAttributes($el);

            // Dealing with height and width is going to be tricky
            // https://github.com/ampproject/amphtml/blob/master/extensions/amp-twitter/amp-twitter.md
            // @todo make this smarter
            /** @var \DOMElement $new_dom_el */
            $el->after("<amp-twitter $tweet_attributes width='400' height='600' layout='responsive' data-tweetid='$tweet_id'></amp-twitter>");
            $new_dom_el = $el->get(0);

            // Remove the blockquote, its children and the twitter script tag that follows after the blockquote
            $el->removeChildren()->remove();
            if (!empty($twitter_script_tag)) {
                $twitter_script_tag->remove();
                $this->addActionTaken(new ActionTakenLine('blockquote.twitter-tweet (with twitter script tag)', ActionTakenType::TWITTER_CONVERTED, $lineno, $context_string));
            } else {
                $this->addActionTaken(new ActionTakenLine('blockquote.twitter-tweet', ActionTakenType::TWITTER_CONVERTED, $lineno, $context_string));
            }

            $this->context->addLineAssociation($new_dom_el, $lineno);
        }

        return $this->warnings;
    }

    /**
     * Get some extra attributes from the blockquote such as data-cards and data-conversation
     * If data-cards=hidden for instance, photos are now shown with the tweet
     * If data-conversation=none for instance, no conversation is shown in the tweet
     *
     * @param DOMQuery $el
     * @return string
     */
    protected function getTweetAttributes(DOMQuery $el)
    {
        $tweet_attributes = '';
        $data_cards_value = $el->attr('data-cards');
        if (!empty($data_cards_value)) {
            $tweet_attributes .= " data-cards='$data_cards_value' ";
        }

        $data_conversation_value = $el->attr('data-conversation');
        if (!empty($data_conversation_value)) {
            $tweet_attributes .= " data-conversation='$data_conversation_value' ";
        }

        return $tweet_attributes;
    }

    /**
     * Get reference to associated <script> tag, if any.
     *
     * @param DOMQuery $el
     * @return DOMQuery|null
     */
    protected function getTwitterScriptTag(DOMQuery $el)
    {
        $script_tags = $el->nextAll('script');
        $twitter_script_tag = null;
        foreach ($script_tags as $script_tag) {
            if (!empty($script_tag) && preg_match('&(*UTF8)twitter.com/widgets\.js&i', $script_tag->attr('src'))) {
                $twitter_script_tag = $script_tag;
                break;
            }
        }

        return $twitter_script_tag;
    }

    /**
     * Get twitter status from the twitter embed code
     */
    protected function getTweetId(DOMQuery $el)
    {
        $links = $el->find('a');
        /** @var DOMQuery $link */
        $tweet_id = '';
        // Get the shortcode from the first <a> tag that matches regex and exit
        foreach ($links as $link) {
            $href = $link->attr('href');
            $matches = [];
            if (preg_match('&(*UTF8)twitter.com/.*/status/([^/]+)&i', $href, $matches)) {
                if (!empty($matches[1])) {
                    $tweet_id = $matches[1];
                    break;
                }
            }
        }

        return $tweet_id;
    }
}
