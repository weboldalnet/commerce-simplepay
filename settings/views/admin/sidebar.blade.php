@if($settings->has_blog)
    <div class="mb-1">
        <a class="menu-point collapsed @if($menuHelper::isActiveMenu($menuHelper::ARTICLES, $url)) active @endif"
           data-toggle="collapse" href="#articleCollapse" role="button"
        >
            <span><i class="fas fa-newspaper mr-1"></i>Blog</span>
            <i class="fa-solid fa-chevron-down"></i>
        </a>
        <div class="collapse collapse-box @if($menuHelper::isActiveMenu($menuHelper::ARTICLES, $url)) show @endif" id="articleCollapse">
            <div class="collapse-menu-points">
                <a href="/article-list" class="fw-800">
                    Cikkek listája <i class="fa-solid fa-chevron-right"></i>
                </a>
                <a href="/article-category-list">
                    Cikk kategóriák <i class="fa-solid fa-chevron-right"></i>
                </a>

                <hr class="d-block w-fill my-1 mx-2">

                <a href="/label-list">
                    Címkék <i class="fa-solid fa-chevron-right"></i>
                </a>
            </div>
        </div>
    </div>
@endif