<?php

namespace Omnireach\Model;

/** What a report's rows are broken down by, in the words every provider is mapped to. */
enum Dimension: string
{
    /** The search query typed (search engines only). */
    case Query = 'query';
    /** A page: shown in search results (search engines), or viewed (analytics). */
    case Page = 'page';
    /** The page a visit started on: the landing page (analytics only). */
    case EntryPage = 'entry_page';
    /** The visitor's country, as the provider gives it (ISO 3166-1 alpha-2 upper case, alpha-3 for Search Console). */
    case Country = 'country';
    /** desktop, mobile, tablet. */
    case Device = 'device';
    /** Where a visit came from: a search engine, a site, "direct" (analytics only). */
    case Source = 'source';
    /** The day, Y-m-d: a period's history. */
    case Date = 'date';
}
