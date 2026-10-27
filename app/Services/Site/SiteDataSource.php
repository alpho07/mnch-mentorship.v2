<?php

namespace App\Services\Site;

/**
 * Every raw value the public pages (home, /resources, /categories) need.
 * Return plain arrays in exactly these shapes; SitePresenter does all formatting and maths.
 *
 * Production: DatabaseSiteData. Tests and previews: Tests\Fixtures\LiveSiteSnapshot.
 */
interface SiteDataSource
{
    /**
     * Headline figures for the home page.
     *
     * @return array{mentorships:int, counties:int, mentees:int, facilities:int, active_mentorships:int,
     *               active_classes:int, pipeline_30d:int, pipeline_counties:int}
     */
    public function homeStats(): array;

    /**
     * Live mentorships per county with the facilities running them, biggest first.
     * County names must match resources/data/kenya-counties.json.
     *
     * @return array<string, list<array{facility:string, count:int}>>
     */
    public function liveMentorshipsByCounty(): array;

    /**
     * Mentorships that are running now, start in the next 30 days, or ended in the last 30 days.
     * closed = the programme has been closed in the app. Otherwise the presenter works out
     * "coming up" (starts after today) or "live" from the dates.
     *
     * @return list<array{programme:string, facility:string, county:string, starts_on:string, ends_on:string, closed:bool}>  dates Y-m-d
     */
    public function mentorshipCalendar(\DateTimeInterface $today): array;

    /**
     * Every published resource.
     *   format: 'Slides'|'Manual'|'PDF'|'Guideline'|'Online'
     *   subcategory: the most specific category the resource is filed under (may equal a top-level category)
     *   thumbnail: public URL, or a path under public/ (e.g. images/site/x.jpg), or null
     *
     * @return list<array{slug:string, title:string, format:string, subcategory:string, views:int, downloads:int,
     *                    created_at:string, advanced:bool, featured:bool, thumbnail:?string, excerpt:?string}>
     */
    public function resources(): array;

    /**
     * Top-level categories with their subcategories.
     *   direct: resources filed on the category itself (not on a subcategory)
     *
     * @return list<array{name:string, slug:string, description:string, direct:int,
     *                    subcategories:list<array{name:string, slug:string, count:int}>}>
     */
    public function categories(): array;
}
