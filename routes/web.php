<?php

use App\Helpers\CustomPageHelper;

use App\Http\Controllers\Admin\Article\ArticleController;

Route::namespace('App\Http\Controllers\Site')->domain(getSiteDomain())->middleware('web', 'site_share')->group(function () {
    /** ----- Blog funkciók ----- */
    Route::middleware('has.blog')->group(function () {
        Route::get('/' . CustomPageHelper::SITE_URL_LIST[CustomPageHelper::BLOG], 'ArticleController@getArticleListPage');
        Route::get('/blog/{categoryName}-{categoryId}', 'ArticleController@getCategoryPage')
            ->where('categoryName', '[a-z0-9-]+')
            ->where('categoryId', '^[c]([0-9]+)');

        Route::get('/cikk/{articleTitle}-{articleId}', 'ArticleController@getArticlePage')
            ->where('articleTitle', '[a-z0-9-]+')
            ->where('articleId', '^[a]([0-9]+)');

        Route::get('/cikkek/kereses', 'ArticleController@getArticleSearchPage');
    });
    Route::get('/get-article-ajax', 'ArticleController@getArticleAjax');
    Route::get('/article-search-ajax', 'ArticleController@searchArticleAjax');
    Route::get('/article-paginate-ajax', 'ArticleController@articlePaginateAjax');

    // --- Címkék -----
    Route::get('/' . CustomPageHelper::SITE_URL_LIST[CustomPageHelper::LABELS], 'LabelController@getLabelListPage');
    Route::get('/cimke/{labelName}-{pageId}', 'LabelController@getLabelPage')
        ->where('labelName', '[a-z0-9-]+')
        ->where('pageId', '^[l]([0-9]+)');
    /** ------------------------ */
});

Route::namespace('App\Http\Controllers\Admin')->domain(getAdminDomain())->middleware('web', 'admin_share')->group(function () {

    Route::middleware('auth:admin')->group(function () {
        Route::namespace('Article')->group(function () {
            // Cikk kategória
            Route::get('/article-category-list', 'ArticleCategoryController@getArticleCategories');
            Route::get('/article-category', 'ArticleCategoryController@getArticleCategory');
            Route::post('/save-article-category', 'ArticleCategoryController@saveArticleCategory');
            Route::get('/update-article-category-order', 'ArticleCategoryController@updateArticleCategoryOrder');
            Route::get('/available-article-category', 'ArticleCategoryController@availableArticleCategory');
            Route::get('/delete-article-category', 'ArticleCategoryController@deleteArticleCategory');
            Route::get('/toggle-rss', 'ArticleCategoryController@toggleRss');

            Route::get('/article-list', 'ArticleController@getArticleList');
            //Route::get('/article-list', [ArticleController::class => 'getArticleList']);
            Route::get('/article-list-filter', 'ArticleController@getArticleListFilterPage');
            Route::get('/article', 'ArticleController@getArticle');
            Route::post('/save-article', 'ArticleController@saveArticle');
            Route::get('/available-article', 'ArticleController@availableArticle');
            Route::get('/highlight-article', 'ArticleController@highlightArticle');

            Route::get('/update-article-gallery-order', 'ArticleController@updateArticleGalleryOrder');

            Route::get('/delete-article', 'ArticleController@deleteArticle');
            Route::get('/delete-article-image', 'ArticleController@deleteArticleImage');

            // Címke management
            Route::get('/label-list', 'LabelController@getLabelList');
            Route::get('/label', 'LabelController@getLabel');
            Route::post('/save-label', 'LabelController@saveLabel');
            Route::get('/delete-label', 'LabelController@deleteLabel');
        });
    });
});
