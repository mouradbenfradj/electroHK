<section class="py-5 mb-5" style="background: url(images/background-pattern.jpg);">
  <div class="container-fluid">
    <div class="d-flex justify-content-between">
      <h1 class="page-title pb-2">{intl l="You are here:"}</h1>
      <nav class="breadcrumb fs-6">
        <a class="breadcrumb-item nav-link" href="{navigate to="index"}">{intl l="Home"}</a>
        {foreach $breadcrumbs as $breadcrumb}
          {if $breadcrumb.title}
            {if $breadcrumb@last}
              <span aria-current="{$breadcrumb.title|unescape}"
                class="breadcrumb-item active">{$breadcrumb.title|unescape}</span>
            {else}
              <a href="{$breadcrumb.url|default:'#' nofilter}" class="breadcrumb-item nav-link" href="#"
                title="{$breadcrumb.title|unescape}">{$breadcrumb.title|unescape}</a>
            {/if}
          {/if}
        {/foreach}
      </nav>
    </div>
  </div>
</section>