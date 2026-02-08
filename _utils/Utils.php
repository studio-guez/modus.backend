<?php

class Utils
{
    /**
     * Resolve tag UUIDs to objects with slug and name
     * Tags are stored as "page://UUID, page://UUID" format
     * 
     * @param string|null $tagsValue The raw tags value from content
     * @param \Kirby\Cms\Site $site The Kirby site instance
     * @return array Array of resolved tags with 'slug' and 'name' keys
     */
    static function resolveTagsFromUuids(?string $tagsValue, \Kirby\Cms\Site $site): array
    {
        if (!$tagsValue) return [];

        $tagUuids = array_filter(array_map('trim', explode(',', $tagsValue)));
        $tagsPage = $site->find('tags');
        $resolvedTags = [];

        if ($tagsPage) {
            foreach ($tagUuids as $tagUuid) {
                $tagPage = $tagsPage->children()->listed()->findBy('uuid', $tagUuid);
                if ($tagPage) {
                    $resolvedTags[] = [
                        'slug' => $tagPage->slug(),
                        'name' => $tagPage->title()->value(),
                    ];
                }
            }
        }

        return $resolvedTags;
    }

    /**
     * Collect unique tags from an array of resolved tags arrays
     * 
     * @param array $allTags Array of resolved tag arrays
     * @return array Unique tags sorted by name
     */
    static function collectUniqueTags(array $allTags): array
    {
        $uniqueTags = [];
        $seenSlugs = [];

        foreach ($allTags as $tags) {
            foreach ($tags as $tag) {
                if (!isset($seenSlugs[$tag['slug']])) {
                    $seenSlugs[$tag['slug']] = true;
                    $uniqueTags[] = $tag;
                }
            }
        }

        usort($uniqueTags, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        return $uniqueTags;
    }

    static function getImageArrayDataInPage(\Kirby\Cms\Files $files): array|null
    {
        return $files->map(function (\Kirby\Cms\File $item): array {
            return self::getJsonEncodeImageData($item);
        })->data();
    }

    static function muteImageFilesDataIfBlocksHasKeyValue(string $contentTypeKey, &$content): void
    {
        if (!isset($content['content'][$contentTypeKey])) return;

        foreach ($content['content'][$contentTypeKey] as &$itemArray) {
            //todo: images with s for profiles importation | change images to image in dataBase and profiles json result
            if (isset($itemArray['images'])) $itemArray['imageData'] = self::getImageArrayDataInArray($itemArray, 'images');
            if (isset($itemArray['image'])) $itemArray['imageData'] = self::getImageArrayDataInArray($itemArray, 'image');
        }
    }

    static function getImageArrayDataInArray(array &$itemArray, string $keyNameForImage): array
    {
        $getImageArrayData = Utils::getImageArrayDataInPage(new \Kirby\Cms\Files($itemArray[$keyNameForImage]));
        return $itemArray['imageData'] = array_values($getImageArrayData);
    }


    static function getJsonEncodeImageData(\Kirby\Cms\File $file): array
    {
        return [
            'focus' => $file->content()->focus()->value(),
            'caption' => $file->caption()->value(),
            'alt' => $file->alt()->value(),
            'link' => $file->link()->value(),
            'photoCredit' => $file->photoCredit()->value(),
            'url' => $file->url(),
            'mediaUrl' => $file->mediaUrl(),
            'width' => $file->width(),
            'height' => $file->height(),
            'resize' => [
                'tiny' => $file->resize(50, null, 10)->url(),
                'small' => $file->resize(500)->url(),
                'reg' => $file->resize(1280)->url(),
                'large' => $file->resize(1920)->url(),
                'xxl' => $file->resize(2500)->url(),
            ]
        ];
    }
}
