<?php
$phName = \Weboldalnet\PackageTemplate\Support\PackageHelper::PACKAGE_NAME;
$phPrefix = \Weboldalnet\PackageTemplate\Support\PackageHelper::PACKAGE_PREFIX;
$phList = \Weboldalnet\PackageTemplate\Support\PackageHelper::PACKAGE_LIST;
$phViewExtends = \Weboldalnet\PackageTemplate\Support\PackageHelper::PACKAGE_VIEW_EXTENDS;
?>
<div class="col-lg-6 mb-3">
    <div class="content-box">
        <h4 class="fw-600">{{$phName}}</h4>

        <ul class="nav nav-tabs border-0 pb-1" id="{{$phPrefix}}Tab" role="tablist">
            @if($phList)
                <li class="nav-item" role="{{$phPrefix}}-phList">
                    <button class="nav-link btn btn-sm btn-secondary fs-16 active" id="home-tab" data-toggle="tab" data-target="#{{$phPrefix}}-phList" type="button" role="tab" aria-controls="{{$phPrefix}}-phList" aria-selected="true">Package emelek</button>
                </li>
            @endif
            @if($phViewExtends)
                <li class="nav-item" role="{{$phPrefix}}-phList">
                    <button class="nav-link btn btn-sm btn-secondary fs-16" id="home-tab" data-toggle="tab" data-target="#{{$phPrefix}}-phView" type="button" role="tab" aria-controls="{{$phPrefix}}-phView">View kiegészítések</button>
                </li>
            @endif
        </ul>
        <div class="tab-content" id="{{$phPrefix}}TabContent">
            @if($phList)
                <div class="tab-pane fade show active" id="{{$phPrefix}}-phList" role="tabpanel" aria-labelledby="{{$phPrefix}}-phList-tab">
                    <table class="table lh-1 fs-16 mb-0">
                        @foreach($phList as $pTagName => $pTagData)
                            <tr>
                                <td class="text-left py-1 ">
                                    <span class="fw-600">{{$pTagName}}:</span>
                                    <p class="mb-0 fs-14 text-secondary">{{$pTagData['name']}}</p>
                                </td>
                                <td class="text-right py-1">
                                    <button type="button"
                                            class="btn btn-sm btn-warning fw-600"
                                            onclick="packageArtisanScript($(this), '{{$phPrefix . ':install --tag=' . $phPrefix . '-' . $pTagName}}')"
                                    >
                                        <i class="fas fa-sync-alt"></i> publish
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                        <tr class="bg-light">
                            <td class="text-left py-1 font-weight-bold">
                                <b class="">all:</b>
                                <p class="mb-0 fs-14 text-secondary">összes</p>
                            </td>
                            <td class="text-right py-1">
                                <button type="button"
                                        class="btn btn-sm btn-warning fw-600"
                                        onclick="packageArtisanScript($(this), '{{$phPrefix . ':install --tag=' . $phPrefix . '-all'}}')"
                                >
                                    <i class="fas fa-sync-alt"></i> publish
                                </button>
                            </td>
                        </tr>
                    </table>
                </div>
            @endif
            @if($phViewExtends)
                <div class="tab-pane fade" id="{{$phPrefix}}-phView" role="tabpanel" aria-labelledby="{{$phPrefix}}-phView-tab">
                    <table class="table lh-1 fs-16 mb-0">
                        @foreach($phViewExtends as $pViewName => $pViewData)
                            <tr>
                                <td class="text-left py-1 ">
                                    <span class="fw-600">{{$pViewName}}:</span>
                                    <p class="mb-0 fs-14 text-secondary">{{$pViewData['include']}}</p>
                                </td>
                                <td class="text-right py-1">
                                    <button type="button"
                                            class="btn btn-sm btn-warning fw-600"
                                            onclick="packageArtisanScript($(this), '{{$phPrefix . ':extend --view=' . $pViewName}}')"
                                    >
                                        <i class="fas fa-sync-alt"></i> append
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                        <tr class="bg-light">
                            <td class="text-left py-1 font-weight-bold">
                                <b class="">all:</b>
                                <p class="mb-0 fs-14 text-secondary">összes</p>
                            </td>
                            <td class="text-right py-1">
                                <button type="button"
                                        class="btn btn-sm btn-warning fw-600"
                                        onclick="packageArtisanScript($(this), '{{$phPrefix . ':extend --view=all'}}')"
                                >
                                    <i class="fas fa-sync-alt"></i> append
                                </button>
                            </td>
                        </tr>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>