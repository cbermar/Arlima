<?php


/**
 * Applies wordpress filters on callback functions invoked
 * when rendering a Arlim list
 *
 * @package Arlima
 * @since 2.5
 */
class Arlima_FilterApplier
{

    private static $width = 468;

    /**
     * @param Arlima_AbstractListRenderingManager $renderer
     */
    public static function bindFilters(&$renderer)
    {
        $renderer->setFuturePostCallback('Arlima_EventBinder::futurePostCallback');

        $renderer->setArticleBeginCallback('Arlima_EventBinder::articleBeginCallback');

        $renderer->setImageCallback('Arlima_EventBinder::imageCallback');
        $renderer->setContentCallback('Arlima_EventBinder::contentCallback');
        $renderer->setRelatedPostsCallback('Arlima_EventBinder::relatedPostCallback');

        $renderer->setArticleEndCallback('Arlima_EventBinder::articleEndCallback');
    }

    /**
     * @param $w
     */
    public static function setArticleWidth($w)
    {
        self::$width = (int)$w;
    }

    /**
     * @return int
     */
    public static function getArticleWidth()
    {
        return self::$width;
    }

    /**
     * @param $article_counter
     * @param $article
     * @param $post
     * @param $list
     * @return string
     */
    public static function articleBeginCallback($article_counter, &$article, $post, $list)
    {
        $filtered = self::filter('arlima_article_begin', $article_counter, $article, $post, $list);
        $article = $filtered['article'];
        return $filtered['content'];
    }

    /**
     * @param $filter
     * @param $article_counter
     * @param $article
     * @param $post
     * @param $list
     * @param bool $img_size
     * @return array
     */
    private static function filter($filter, $article_counter, $article, $post, $list, $img_size=false)
    {
        $data = array(
            'article' => $article,
            'count' => $article_counter,
            'post' => $post,
            'content' => '',
            'list' => $list
        );
        if($img_size) {
            $data['size_name'] = $img_size;
            $data['width'] = self::$width;
        }

        return apply_filters($filter, $data);
    }

    /**
     * @param $article_counter
     * @param $article
     * @param $post
     * @param $list
     * @return mixed
     */
    public static function articleEndCallback($article_counter, $article, $post, $list)
    {
        $filtered = self::filter('arlima_article_end', $article_counter, $article, $post, $list);
        return $filtered['content'];
    }

    /**
     * @param $article_counter
     * @param $article
     * @param $post
     * @param $list
     * @param $img_size
     * @return string
     */
    public static function imageCallback($article_counter, $article, $post, $list, $img_size)
    {

        $filtered = self::filter('arlima_article_image', $article_counter, $article, $post, $list, $img_size);

        // Fallback for images
        if ( empty($filtered['content']) ) {

            $has_img = !empty($article['image_options']) && !empty($article['image_options']['attach_id']);
            $has_giant_tmpl = !empty($article['options']['template']) && $article['options']['template'] == 'giant';

            if ( $has_img && !$has_giant_tmpl ) {

                $attach_meta = wp_get_attachment_metadata($article['image_options']['attach_id']);
                if ( !$attach_meta ) {
                    return false;
                }

                $article_width = empty($article['parent']) || $article['parent'] == -1 ? self::$width : round(
                    self::$width * 0.5
                );

                switch ($article['image_options']['size']) {
                    case 'half':
                        $width = round($article_width * 0.5);
                        $size = array($width, round($attach_meta['height'] * ($width / $attach_meta['width'])));
                        break;
                    case 'third':
                        $width = round($article_width * 0.33);
                        $size = array($width, round($attach_meta['height'] * ($width / $attach_meta['width'])));
                        break;
                    case 'quarter':
                        $width = round($article_width * 0.25);
                        $size = array($width, round($attach_meta['height'] * ($width / $attach_meta['width'])));
                        break;
                    default:
                        $size = array(
                            $article_width,
                            round($attach_meta['height'] * ($article_width / $attach_meta['width']))
                        );
                        break;
                }

                $img_class = $article['image_options']['size'] . ' ' . $article['image_options']['alignment'];
                $img_alt = htmlspecialchars($article['title']);
                $attach_url = wp_get_attachment_url($article['image_options']['attach_id']);
                $resized_img = image_resize(
                    WP_CONTENT_DIR . '/uploads/' . $attach_meta['file'],
                    $size[0],
                    null,
                    false,
                    null,
                    null,
                    98
                );
                if ( !is_wp_error($resized_img) ) {
                    $img_url = dirname($attach_url) . '/' . basename($resized_img);
                } else {
                    $img_url = $attach_url;
                }

                $filtered['content'] = sprintf(
                    '<img src="%s" width="%s" alt="%s" class="%s" />',
                    $img_url,
                    $size[0],
                    $img_alt,
                    $img_class
                );
            }
        }

        return $filtered['content'];
    }

    /**
     * @param $article_counter
     * @param $article
     * @param $post
     * @param $list
     * @return string
     */
    public static function futurePostCallback($article_counter, $article, $post, $list)
    {
        $filtered = self::filter('arlima_future_post', $article_counter, $article, $post, $list);

        $message = '<div class="arlima future-post">
                        Hey dude, <a href="' . admin_url('post.php?action=edit&amp;post=' . $post->ID) . '" target="_blank">this post</a>
                        will not show up in the list until it\'s published, unless you\'re not previewing the list that is...
                    </div>';

        return empty($filtered['content']) ? $message : $filtered['content'];
    }

    /**
     * @param $article_counter
     * @param $article
     * @param $post
     * @param $list
     * @return string
     */
    public static function contentCallback($article_counter, $article, $post, $list)
    {
        $filtered = self::filter('arlima_article_content', $article_counter, $article, $post, $list);

        if( empty($filtered['content']) ) {
            return arlima_link_entrywords(trim($article['text']), $article['url']);
        } else {
            return $filtered['content'];
        }
    }

    /**
     * @param $article_counter
     * @param $article
     * @param $post
     * @param $list
     * @return array|bool|string
     */
    public static function relatedPostsCallback($article_counter, $article, $post, $list)
    {
        $filtered = self::filter('arlima_article_related_content', $article_counter, $article, $post, $list);

        if ( empty($filtered['content']) ) {
            return !empty($post) ? arlima_related_posts('inline', null, false) : '';
        } else {
            return $filtered['content'];
        }
    }
}
