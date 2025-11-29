<?php

use RedBeanPHP\R as R;

function fetchPostType(?int $postTypeId): ?array
{
    if (!$postTypeId) {
        return null;
    }

    $type = R::getRow('SELECT * FROM post_type WHERE id = ?', [$postTypeId]);

    return $type ?: null;
}

function fetchAllPostTypes(): array
{
    return R::getAll('SELECT * FROM post_type ORDER BY name ASC');
}

function fetchAllCategories(): array
{
    return R::getAll('SELECT * FROM category ORDER BY name ASC');
}

function fetchAllTags(): array
{
    return R::getAll('SELECT * FROM tag ORDER BY name ASC');
}

function fetchPostCategories(int $postId): array
{
    if (!$postId) {
        return [];
    }

    return R::getAll(
        'SELECT c.* FROM category c INNER JOIN post_category pc ON pc.category_id = c.id WHERE pc.post_id = ? ORDER BY c.name ASC',
        [$postId]
    );
}

function fetchPostTags(int $postId): array
{
    if (!$postId) {
        return [];
    }

    return R::getAll(
        'SELECT t.* FROM tag t INNER JOIN post_tag pt ON pt.tag_id = t.id WHERE pt.post_id = ? ORDER BY t.name ASC',
        [$postId]
    );
}

function fetchPostCategoryIds(int $postId): array
{
    $rows = R::getAll('SELECT category_id FROM post_category WHERE post_id = ?', [$postId]);

    return array_map(fn ($row) => (int) $row['category_id'], $rows);
}

function fetchPostTagIds(int $postId): array
{
    $rows = R::getAll('SELECT tag_id FROM post_tag WHERE post_id = ?', [$postId]);

    return array_map(fn ($row) => (int) $row['tag_id'], $rows);
}

function hydratePostWithRelations($post): void
{
    $post->type = fetchPostType((int) ($post->post_type_id ?? 0));
    $post->categories = fetchPostCategories((int) ($post->id ?? 0));
    $post->tags_list = fetchPostTags((int) ($post->id ?? 0));
    $post->tag_names = array_map(fn ($tag) => $tag['name'], $post->tags_list);
}

function syncPostRelations(int $postId, array $ids, string $linkTable, string $columnName): void
{
    R::exec(sprintf('DELETE FROM `%s` WHERE `post_id` = ?', $linkTable), [$postId]);

    if (!$ids) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '(?, ?)'));
    $values = [];

    foreach ($ids as $id) {
        $values[] = $postId;
        $values[] = (int) $id;
    }

    R::exec(
        sprintf('INSERT INTO `%s` (`post_id`, `%s`) VALUES %s', $linkTable, $columnName, $placeholders),
        $values
    );
}

function fetchTagNamesByIds(array $tagIds): array
{
    if (!$tagIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($tagIds), '?'));

    $rows = R::getAll(
        sprintf('SELECT name FROM tag WHERE id IN (%s) ORDER BY name ASC', $placeholders),
        array_map('intval', $tagIds)
    );

    return array_map(fn ($row) => $row['name'], $rows);
}
