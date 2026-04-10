<!doctype html>
<!--
 ______   __  __     ______     __         __     ______
/\__  _\ /\ \_\ \   /\  ___\   /\ \       /\ \   /\  __ \
\/_/\ \/ \ \  __ \  \ \  __\   \ \ \____  \ \ \  \ \  __ \
   \ \_\  \ \_\ \_\  \ \_____\  \ \_____\  \ \_\  \ \_\ \_\
    \/_/   \/_/\/_/   \/_____/   \/_____/   \/_/   \/_/\/_/


Copyright (c) OpenStudio
email : info@thelia.net
web : http://www.thelia.net

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the
GNU General Public License : http://www.gnu.org/licenses/
-->

{* Declare assets directory, relative to template base directory *}
{declare_assets directory='assets/dist'}
{* Set the default translation domain, that will be used by {intl} when the 'd' parameter is not set *}
{default_translation_domain domain='fo.default'}

{* -- Define some stuff for Smarty ------------------------------------------ *}
{config_load file='variables.conf'}
{block name="init"}{/block}
{block name="no-return-functions"}{/block}
{assign var="store_name" value={config key="store_name"}}
{assign var="store_description" value={config key="store_description"}}
{assign var="lang_code" value={lang attr="code"}}
{assign var="lang_locale" value={lang attr="locale"}}
{if not $store_name}{assign var="store_name" value={intl l='Thelia V2'}}{/if}
{if not $store_description}{assign var="store_description" value={$store_name}}{/if}

{* paulirish.com/2008/conditional-stylesheets-vs-css-hacks-answer-neither *}
<!--[if lt IE 7 ]><html class="no-js oldie ie6" lang="{$lang_code}"> <![endif]-->
<!--[if IE 7 ]><html class="no-js oldie ie7" lang="{$lang_code}"> <![endif]-->
<!--[if IE 8 ]><html class="no-js oldie ie8" lang="{$lang_code}"> <![endif]-->
<!--[if (gte IE 9)|!(IE)]><!--><html lang="{$lang_code}" class="no-js"> <!--<![endif]-->
<head>
    {hook name="main.head-top"}
    {* Test if javascript is enabled *}
    <script>(function(H) { H.className=H.className.replace(/\bno-js\b/,'js') } )(document.documentElement);</script>

    <meta charset="utf-8">

    {* Page Title *}
    <title>{block name="page-title"}{strip}{if $page_title}{$page_title}{elseif $breadcrumbs}{foreach from=$breadcrumbs|array_reverse item=breadcrumb}{$breadcrumb.title|unescape} - {/foreach}{$store_name}{else}{$store_name}{/if}{/strip}{/block}</title>

    {* Meta Tags *}
    <meta name="generator" content="{intl l='Thelia V2'}">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    {block name="meta"}
        <meta name="description" content="{if $page_description}{$page_description}{else}{$store_description|strip|truncate:120}{/if}">
    {/block}
{* Thelia CSS commented to avoid conflicts *}
{* {stylesheets file='assets/dist/css/thelia.min.css'}
        <link rel="stylesheet" href="{$asset_url}">
{/stylesheets} *}

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

    {*

    If you want to generate the CSS assets on the fly, just replace the stylesheet inclusion above by the following.
     Then, in your back-office, go to Configuration -> System Variables and set process_assets to 1.
     Now, when you're accessing the front office in developpement mode (index_dev.php)  the CSS is recompiled when a
     change in the source files is detected.

     See http://doc.thelia.net/en/documentation/templates/assets.html#activate-automatic-assets-generation for details.

    {stylesheets file='assets/src/less/thelia.less' filters='less'}
        <link rel="stylesheet" href="{$asset_url}">
    {/stylesheets}

    *}

    {hook name="main.stylesheet"}

    {block name="stylesheet"}{/block}

        <style type="text/tailwindcss">
            .electro-navigation {
                left: calc(0px + var(--anchor-offset, 0px));
                top: calc(104px + var(--anchor-gap, 0px));
                --button-width: 50.875px;
                position: absolute;
            }
        </style>

{stylesheets file='assets/css/custom.css'}
    <link rel="stylesheet" href="{$asset_url}">
{/stylesheets}
    {* Favicon *}
    {* PNG file favicons are not supported by IE 10 and lower. In this case, we use the default .ico file in the template. *}

    <!--[if lt IE 11]>
    <link rel="shortcut icon" type="image/x-icon" href="{image file='assets/dist/img/favicon.ico'}" />
    <![endif]-->

    {local_media type="favicon" width=32 height=32}
    <link rel="icon" type="{$MEDIA_MIME_TYPE}" href="{$MEDIA_URL}" />
    {/local_media}

    {* Feeds *}
    <link rel="alternate" type="application/rss+xml" title="{intl l='All products'}" href="{url path="/feed/catalog/%lang" lang=$lang_locale}" />
    <link rel="alternate" type="application/rss+xml" title="{intl l='All contents'}" href="{url path="/feed/content/%lang" lang=$lang_locale}" />
    <link rel="alternate" type="application/rss+xml" title="{intl l='All brands'}"   href="{url path="/feed/brand/%lang" lang=$lang_locale}" />
    {block name="feeds"}{/block}

    {* HTML5 shim and Respond.js IE8 support of HTML5 elements and media queries *}
    <!--[if lt IE 9]>
    <script src="//cdnjs.cloudflare.com/ajax/libs/html5shiv/3.7.3/html5shiv.js"></script>
    {javascripts file="assets/dist/js/vendors/html5shiv.min.js"}
        <script>window.html5 || document.write('<script src="{$asset_url}"><\/script>');</script>
    {/javascripts}

    <script src="//cdnjs.cloudflare.com/ajax/libs/respond.js/1.4.2/respond.js"></script>
    {javascripts file="assets/dist/js/vendors/respond.min.js"}
        <script>window.respond || document.write('<script src="{$asset_url}"><\/script>');</script>
    {/javascripts}
    <![endif]-->
<!-- Include this script tag or install `@tailwindplus/elements` via npm: -->
<script src="https://cdn.jsdelivr.net/npm/@tailwindplus/elements@1" type="module"></script>

    {hook name="main.head-bottom"}
</head>
<body class="{block name="body-class"}{/block}" itemscope itemtype="http://schema.org/WebPage">
    {hook name="main.body-top"}
<!-- Include this script tag or install `@tailwindplus/elements` via npm: -->
<!-- <script src="https://cdn.jsdelivr.net/npm/@tailwindplus/elements@1" type="module"></script> -->
<div class="bg-white">
  <!-- Mobile menu -->
  <el-dialog>
    <dialog id="mobile-menu" class="backdrop:bg-transparent lg:hidden">
      <el-dialog-backdrop class="fixed inset-0 bg-black/25 transition-opacity duration-300 ease-linear data-closed:opacity-0"></el-dialog-backdrop>
      <div tabindex="0" class="fixed inset-0 flex focus:outline-none">
        <el-dialog-panel class="relative flex w-full max-w-xs transform flex-col overflow-y-auto bg-white pb-12 shadow-xl transition duration-300 ease-in-out data-closed:-translate-x-full">
          <div class="flex px-4 pt-5 pb-2">
            <button type="button" command="close" commandfor="mobile-menu" class="relative -m-2 inline-flex items-center justify-center rounded-md p-2 text-gray-400">
              <span class="absolute -inset-0.5"></span>
              <span class="sr-only">Close menu</span>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" data-slot="icon" aria-hidden="true" class="size-6">
                <path d="M6 18 18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </button>
          </div>

          <!-- Links -->
          <el-tab-group class="mt-2 block">
            <div class="border-b border-gray-200">
              <el-tab-list class="-mb-px flex space-x-8 px-4">
                <button class="flex-1 border-b-2 border-transparent px-1 py-4 text-base font-medium whitespace-nowrap text-gray-900 aria-selected:border-indigo-600 aria-selected:text-indigo-600">Women 2</button>
                <button class="flex-1 border-b-2 border-transparent px-1 py-4 text-base font-medium whitespace-nowrap text-gray-900 aria-selected:border-indigo-600 aria-selected:text-indigo-600">Men</button>
              </el-tab-list>
            </div>
            <el-tab-panels>
              <div class="space-y-10 px-4 pt-10 pb-8">
                <div class="grid grid-cols-2 gap-x-4">
                  <div class="group relative text-sm">
                    <img src="https://tailwindcss.com/plus-assets/img/ecommerce-images/mega-menu-category-01.jpg" alt="Models sitting back to back, wearing Basic Tee in black and bone." class="aspect-square w-full rounded-lg bg-gray-100 object-cover group-hover:opacity-75" />
                    <a href="#" class="mt-6 block font-medium text-gray-900">
                      <span aria-hidden="true" class="absolute inset-0 z-10"></span>
                      New Arrivals
                    </a>
                    <p aria-hidden="true" class="mt-1">Shop now</p>
                  </div>
                  <div class="group relative text-sm">
                    <img src="https://tailwindcss.com/plus-assets/img/ecommerce-images/mega-menu-category-02.jpg" alt="Close up of Basic Tee fall bundle with off-white, ochre, olive, and black tees." class="aspect-square w-full rounded-lg bg-gray-100 object-cover group-hover:opacity-75" />
                    <a href="#" class="mt-6 block font-medium text-gray-900">
                      <span aria-hidden="true" class="absolute inset-0 z-10"></span>
                      Basic Tees
                    </a>
                    <p aria-hidden="true" class="mt-1">Shop now</p>
                  </div>
                </div>
                <div>
                  <p id="women-clothing-heading-mobile" class="font-medium text-gray-900">Clothing</p>
                  <ul role="list" aria-labelledby="women-clothing-heading-mobile" class="mt-6 flex flex-col space-y-6">
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">Tops</a>
                    </li>
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">Dresses</a>
                    </li>
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">Pants</a>
                    </li>
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">Denim</a>
                    </li>
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">Sweaters</a>
                    </li>
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">T-Shirts</a>
                    </li>
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">Jackets</a>
                    </li>
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">Activewear</a>
                    </li>
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">Browse All</a>
                    </li>
                  </ul>
                </div>
                <div>
                  <p id="women-accessories-heading-mobile" class="font-medium text-gray-900">Accessories</p>
                  <ul role="list" aria-labelledby="women-accessories-heading-mobile" class="mt-6 flex flex-col space-y-6">
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">Watches</a>
                    </li>
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">Wallets</a>
                    </li>
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">Bags</a>
                    </li>
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">Sunglasses</a>
                    </li>
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">Hats</a>
                    </li>
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">Belts</a>
                    </li>
                  </ul>
                </div>
                <div>
                  <p id="women-brands-heading-mobile" class="font-medium text-gray-900">Brands</p>
                  <ul role="list" aria-labelledby="women-brands-heading-mobile" class="mt-6 flex flex-col space-y-6">
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">Full Nelson</a>
                    </li>
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">My Way</a>
                    </li>
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">Re-Arranged</a>
                    </li>
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">Counterfeit</a>
                    </li>
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">Significant Other</a>
                    </li>
                  </ul>
                </div>
              </div>
              <div hidden class="space-y-10 px-4 pt-10 pb-8">
                <div class="grid grid-cols-2 gap-x-4">
                  <div class="group relative text-sm">
                    <img src="https://tailwindcss.com/plus-assets/img/ecommerce-images/product-page-04-detail-product-shot-01.jpg" alt="Drawstring top with elastic loop closure and textured interior padding." class="aspect-square w-full rounded-lg bg-gray-100 object-cover group-hover:opacity-75" />
                    <a href="#" class="mt-6 block font-medium text-gray-900">
                      <span aria-hidden="true" class="absolute inset-0 z-10"></span>
                      New Arrivals
                    </a>
                    <p aria-hidden="true" class="mt-1">Shop now</p>
                  </div>
                  <div class="group relative text-sm">
                    <img src="https://tailwindcss.com/plus-assets/img/ecommerce-images/category-page-02-image-card-06.jpg" alt="Three shirts in gray, white, and blue arranged on table with same line drawing of hands and shapes overlapping on front of shirt." class="aspect-square w-full rounded-lg bg-gray-100 object-cover group-hover:opacity-75" />
                    <a href="#" class="mt-6 block font-medium text-gray-900">
                      <span aria-hidden="true" class="absolute inset-0 z-10"></span>
                      Artwork Tees
                    </a>
                    <p aria-hidden="true" class="mt-1">Shop now</p>
                  </div>
                </div>
                <div>
                  <p id="men-clothing-heading-mobile" class="font-medium text-gray-900">Clothing</p>
                  <ul role="list" aria-labelledby="men-clothing-heading-mobile" class="mt-6 flex flex-col space-y-6">
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">Tops</a>
                    </li>
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">Pants</a>
                    </li>
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">Sweaters</a>
                    </li>
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">T-Shirts</a>
                    </li>
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">Jackets</a>
                    </li>
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">Activewear</a>
                    </li>
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">Browse All</a>
                    </li>
                  </ul>
                </div>
                <div>
                  <p id="men-accessories-heading-mobile" class="font-medium text-gray-900">Accessories</p>
                  <ul role="list" aria-labelledby="men-accessories-heading-mobile" class="mt-6 flex flex-col space-y-6">
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">Watches</a>
                    </li>
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">Wallets</a>
                    </li>
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">Bags</a>
                    </li>
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">Sunglasses</a>
                    </li>
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">Hats</a>
                    </li>
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">Belts</a>
                    </li>
                  </ul>
                </div>
                <div>
                  <p id="men-brands-heading-mobile" class="font-medium text-gray-900">Brands</p>
                  <ul role="list" aria-labelledby="men-brands-heading-mobile" class="mt-6 flex flex-col space-y-6">
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">Re-Arranged</a>
                    </li>
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">Counterfeit</a>
                    </li>
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">Full Nelson</a>
                    </li>
                    <li class="flow-root">
                      <a href="#" class="-m-2 block p-2 text-gray-500">My Way</a>
                    </li>
                  </ul>
                </div>
              </div>
            </el-tab-panels>
          </el-tab-group>

          <div class="space-y-6 border-t border-gray-200 px-4 py-6">
            <div class="flow-root">
              <a href="#" class="-m-2 block p-2 font-medium text-gray-900">Company</a>
            </div>
            <div class="flow-root">
              <a href="#" class="-m-2 block p-2 font-medium text-gray-900">Stores</a>
            </div>
          </div>

          <div class="space-y-6 border-t border-gray-200 px-4 py-6">
            <div class="flow-root">
              <a href="#" class="-m-2 block p-2 font-medium text-gray-900">Sign in</a>
            </div>
            <div class="flow-root">
              <a href="#" class="-m-2 block p-2 font-medium text-gray-900">Create account</a>
            </div>
          </div>

          <div class="border-t border-gray-200 px-4 py-6">
            <a href="#" class="-m-2 flex items-center p-2">
              <img src="https://tailwindcss.com/plus-assets/img/flags/flag-canada.svg" alt="" class="block h-auto w-5 shrink-0" />
              <span class="ml-3 block text-base font-medium text-gray-900">CAD</span>
              <span class="sr-only">, change currency</span>
            </a>
          </div>
        </el-dialog-panel>
      </div>
    </dialog>
  </el-dialog>

<header class="relative bg-white/90 backdrop-blur-md shadow-lg z-50">
    {hook name="main.header-top"}

    <p class="flex h-10 items-center justify-center bg-indigo-600 px-4 text-sm font-medium text-white sm:px-6 lg:px-8">Get free delivery on orders over $100</p>

    <div aria-label="Top" class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <div class="border-b border-gray-200">
        <div class="flex h-16 items-center">
          <button type="button" command="show-modal" commandfor="mobile-menu" class="relative rounded-md bg-white p-2 text-gray-400 lg:hidden">
            <span class="absolute -inset-0.5"></span>
            <span class="sr-only">Open menu</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" data-slot="icon" aria-hidden="true" class="size-6">
              <path d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </button>

          <!-- Logo -->
          <div class="ml-4 flex lg:ml-0">
            <a href="#">
              <span class="sr-only">Your Company</span>
              <a href="{navigate to="index"}" title="{$store_name}">
                  {local_media type="logo"}
                  <img src="{$MEDIA_URL}" alt="{$store_name}"  class="h-15 w-auto">
                  {/local_media}
              </a>
            </a>
          </div>

                    <div class="navbar-header">
                        <!-- .navbar-toggle is used as the toggle for collapsed navbar content -->
                        <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".nav-secondary">
                            <span class="sr-only">{intl l="Toggle navigation"}</span>
                            <span class="icon-bar"></span>
                            <span class="icon-bar"></span>
                            <span class="icon-bar"></span>
                        </button>
                        <a class="navbar-brand visible-xs" href="{navigate to="index"}">{$store_name}</a>
                    </div>

                    {ifhook rel="main.navbar-secondary"}
                        {* Place everything within .nav-collapse to hide it until above 768px *}
                        <nav class="navbar-collapse collapse nav-secondary" role="navigation" aria-label="{intl l="Secondary Navigation"}">
                            {hook name="main.navbar-secondary"}
                        </nav>
                    {/ifhook}

          <div class="ml-auto flex items-center">
            <div class="hidden lg:flex lg:flex-1 lg:items-center lg:justify-end lg:space-x-6">
              <a href="#" class="text-sm font-medium text-gray-700 hover:text-gray-800">Sign in</a>
              <span aria-hidden="true" class="h-6 w-px bg-gray-200"></span>
              <a href="#" class="text-sm font-medium text-gray-700 hover:text-gray-800">Create account</a>
            </div>

            <div class="hidden lg:ml-8 lg:flex">
              <a href="#" class="flex items-center text-gray-700 hover:text-gray-800">
                <img src="https://tailwindcss.com/plus-assets/img/flags/flag-canada.svg" alt="" class="block h-auto w-5 shrink-0" />
                <span class="ml-3 block text-sm font-medium">CAD</span>
                <span class="sr-only">, change currency</span>
              </a>
            </div>

            <!-- Search -->
            <div class="flex lg:ml-6">
              <a href="#" class="p-2 text-gray-400 hover:text-gray-500">
                <span class="sr-only">Search</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" data-slot="icon" aria-hidden="true" class="size-6">
                  <path d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </a>
            </div>

            <!-- Cart -->
            <div class="ml-4 flow-root lg:ml-6">
              <a href="#" class="group -m-2 flex items-center p-2">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" data-slot="icon" aria-hidden="true" class="size-6 shrink-0 text-gray-400 group-hover:text-gray-500">
                  <path d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <span class="ml-2 text-sm font-medium text-gray-700 group-hover:text-gray-800">0</span>
                <span class="sr-only">items in cart, view bag</span>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
    <nav aria-label="Top" class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

          <!-- Flyout menus -->
          <el-popover-group class="group/popover-group hidden lg:ml-8 lg:block lg:self-stretch">
            <div class="flex h-full space-x-8">
            {hook name="main.navbar-primary"}
            </div>
          </el-popover-group>
    </nav>
    
<div id="categories-swiper" class="swip hidden-categories-swiper relative my-4 h-[20vh] max-h-[150px]">
  <div class="absolute inset-0 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 xl:grid-cols-11 gap-3 p-3 overflow-auto scrollbar-thin scrollbar-thumb-gray-400 scrollbar-track-gray-100">
    {loop type="category" name="hidden-cat" parent="0" visible="0" orderby="position"}
    <a href="{$URL}" class="group flex flex-col items-center justify-center text-center transition-all duration-200 hover:scale-[1.05]">
      <div class="relative h-14 w-14 shadow-lg group-hover:shadow-xl flex-shrink-0">
        {if $IMAGE}
        <img src="{$IMAGE}" alt="{$TITLE}" class="h-full w-full rounded-full object-cover border-3 border-white shadow-md" />
        {else}
        <div class="h-full w-full rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold text-xs shadow-md">{$TITLE|truncate:2:""}</div>
        {/if}
      </div>
      <span class="text-[10px] font-medium text-gray-700 group-hover:text-indigo-600 max-w-[60px] truncate leading-tight mt-1 px-1 block">{$TITLE}</span>
    </a>
    {/loop}
  </div>
</div>

    {hook name="main.header-bottom"}
  </header>
</div>

                {hook name="main.content-top"}
                {block name="breadcrumb"}{include file="misc/breadcrumb.tpl"}{/block}
                <div id="content">{block name="main-content"}{/block}</div>
                {hook name="main.content-bottom"}

{ifhook rel="main.footer-top"}
                <section class="footer-block">
                    <div class="container">
                        <div class="blocks row">
                            {hook name="main.footer-top"}
                        </div>
                    </div>
                </section>
            {/ifhook}
            {elsehook rel="main.footer-top"}
                <section class="footer-banner">
                    <div class="container">
                        <div class="banner row banner-col-3">
                            <div class="col col-sm-4">
                                <span class="fa fa-truck fa-flip-horizontal"></span>
                                {intl l="Free shipping"} <small>{intl l="Orders over $50"}</small>
                            </div>
                            <div class="col col-sm-4">
                                <span class="fa fa-credit-card"></span>
                                {intl l="Secure payment"} <small>{intl l="Multi-payment platform"}</small>
                            </div>
                            <div class="col col-sm-4">
                                <span class="fa fa-info"></span>
                                {intl l="Need help ?"} <small>{intl l="Questions ? See our F.A.Q."}</small>
                            </div>
                        </div>
                    </div>
                </section><!-- /.footer-banner -->
            {/elsehook}

            {ifhook rel="main.footer-body"}
                <section class="footer-block">
                    <div class="container">
                        <div class="blocks row">
                            {hookblock name="main.footer-body"  fields="id,class,title,content"}
                            {forhook rel="main.footer-body"}
                                <div class="col col-sm-3">
                                    <section {if $id} id="{$id}"{/if} class="block {if $class} block-{$class}{/if}">
                                        <div class="block-heading"><h3 class="block-title">{$title}</h3></div>
                                        <div class="block-content">
                                            {$content nofilter}
                                        </div>
                                    </section>
                                </div>
                            {/forhook}
                            {/hookblock}
                        </div>
                    </div>
                </section>
            {/ifhook}

            {ifhook rel="main.footer-bottom"}
                <footer class="footer-info" role="contentinfo">
                    <div class="container">
                        <div class="info row">
                            <div class="col-lg-9">
                                {hook name="main.footer-bottom"}
                            </div>
                            <div class="col-lg-3">
                                <section class="copyright">{intl l="Copyright"} &copy; <time datetime="{'Y-m-d'|date}">{'Y'|date}</time> <a href="http://thelia.net" rel="external">Thelia</a></section>
                            </div>
                        </div>
                    </div>
                </footer>
            {/ifhook}
            {elsehook rel="main.footer-bottom"}
                <footer class="footer-info" role="contentinfo">
                    <div class="container">
                        <div class="info row">
                            <nav class="nav-footer col-lg-9" role="navigation">
                                <ul class="list-unstyled list-inline">
                                    {$folder_information={config key="information_folder_id"}}
                                    {if $folder_information}
                                        {loop name="footer_links" type="content" folder=$folder_information}
                                            <li><a href="{$URL nofilter}">{$TITLE}</a></li>
                                        {/loop}
                                    {/if}
                                    <li><a href="{url path="/contact"}">{intl l="Contact Us"}</a></li>
                                </ul>
                            </nav>
                            <section class="copyright col-lg-3">{intl l="Copyright"} &copy; <time datetime="{'Y-m-d'|date}">{'Y'|date}</time> <a href="http://thelia.net" rel="external">Thelia</a></section>
                        </div>
                    </div>
                </footer><!-- /.footer-info -->
            {/elsehook}    

    {block name="before-javascript-include"}{/block}
    <!-- JavaScript -->

    <!-- Jquery -->
    <!--[if lt IE 9]><script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script> <![endif]-->
    <!--[if (gte IE 9)|!(IE)]><!--><script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script><!--<![endif]-->
    {javascripts file="assets/dist/js/vendors/jquery.min.js"}
        <script>window.jQuery || document.write('<script src="{$asset_url}"><\/script>');</script>
    {/javascripts}

    <script src="//ajax.aspnetcdn.com/ajax/jquery.validate/1.13.1/jquery.validate.min.js"></script>
    {* do no try to load messages_en, as this file does not exists *}
    {if $lang_code != 'en'}
        <script src="//ajax.aspnetcdn.com/ajax/jquery.validate/1.13.1/localization/messages_{$lang_code}.js"></script>
    {/if}

    <script src="//maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js"></script>
    {javascripts file="assets/dist/js/vendors/bootstrap.min.js"}
        <script>if(typeof($.fn.modal) === 'undefined') { document.write('<script src="{$asset_url}"><\/script>'); }</script>
    {/javascripts}

    {javascripts file="assets/dist/js/vendors/bootbox.js"}
        <script src="{$asset_url}"></script>
    {/javascripts}

    {hook name="main.after-javascript-include"}

    {block name="after-javascript-include"}{/block}

    {hook name="main.javascript-initialization"}
    <script>
       // fix path for addCartMessage
       // if you use '/' in your URL rewriting, the cart message is not displayed
       // addCartMessageUrl is used in thelia.js to update the mini-cart content
       var addCartMessageUrl = "{url path='ajax/addCartMessage'}";
    </script>
    {block name="javascript-initialization"}{/block}

    <!-- Custom scripts -->
<script src="{javascript file='assets/dist/js/thelia.min.js'}"></script>

    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
{javascripts file='assets/js/custom.js'}
    <script src="{$asset_url}"></script>
{/javascripts}

    {hook name="main.body-bottom"}
</body>
</html>
