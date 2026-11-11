#!/usr/bin/env python3
"""SEO pass for the static pages. Re-runnable: run it after editing copy.

    python3 tools/seo.py

- Rewrites the <head> SEO block of each modern page (between <!-- seo:start --> and
  <!-- seo:end -->): title, description, canonical, Open Graph, Twitter, icons,
  resource hints, and one JSON-LD @graph (WebSite, business, Person, WebPage,
  breadcrumbs, plus the services catalog on /services and FAQPage on /contact).
  FAQ answers and prices are read from the page itself so they can't drift.
- Rewrites internal links to clean URLs (/services, /about, ...).
- Writes sitemap.xml, robots.txt and llms.txt.
Not deployed (tools/ is excluded in .github/workflows/deploy.yml).
"""
import datetime, html, json, os, re

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SITE = 'https://mikebilleter.com'
BRAND = 'Mike Billeter Creative'
EMAIL = 'mike.billeter@gmail.com'
TODAY = datetime.date.today().isoformat()
OG_IMAGE = SITE + '/assets/og-image.jpg'
HEADSHOT = SITE + '/assets/mike-square-800.jpg'

PAGES = {
    'index': dict(path='/', crumb='Home', type='WebPage',
        title='Copywriter in Sioux Falls, SD — Mike Billeter Creative',
        og_title='Copy that sounds like somebody wrote it — Mike Billeter Creative',
        desc='Mike Billeter is a freelance copywriter in Sioux Falls, SD, writing website copy, email campaigns, blog posts and ghostwritten pieces that sound human. Prices listed.'),
    'services': dict(path='/services', crumb='Services', type='WebPage',
        title='Copywriting Services & Prices — Mike Billeter Creative',
        desc='Website copy from $900, email campaigns from $650, blog posts from $300, editing and ghostwriting at $150/hour, retainers from $450/month. Real prices, listed.'),
    'process': dict(path='/process', crumb='Process', type='WebPage',
        title='How a Copywriting Project Works — Mike Billeter Creative',
        desc='Six steps from first email to final delivery: a real conversation, a project agreement and deposit, the first draft, consolidated feedback, and final files.'),
    'about': dict(path='/about', crumb='About', type='AboutPage',
        title='About Mike Billeter, Copywriter — Mike Billeter Creative',
        desc='Sioux Falls copywriter Mike Billeter walked across the United States, then spent almost twenty years writing for food brands, banks, universities and non-profits.'),
    'contact': dict(path='/contact', crumb='Contact & FAQs', type='ContactPage',
        title='Hire a Copywriter: Contact & FAQs — Mike Billeter Creative',
        desc='Tell Mike what you need written and when. Answers to common questions on timelines, revisions, starting small and what to send. Replies within a day or two.'),
}

KNOWS = ['Copywriting', 'Website copy', 'Email marketing copy', 'Editing', 'Blog writing', 'Ghostwriting', 'Brand voice', 'Content strategy']


def text(s):
    return html.unescape(' '.join(re.sub(r'<[^>]+>', ' ', s).split()))


def read(p):
    with open(os.path.join(ROOT, p), encoding='utf-8') as f:
        return f.read()


def write(p, s):
    with open(os.path.join(ROOT, p), 'w', encoding='utf-8') as f:
        f.write(s)


def faq(page_html):
    out = []
    for q, a in re.findall(r'<details[^>]*>\s*<summary[^>]*>(.*?)</summary>(.*?)</details>', page_html, re.S):
        q = re.sub(r'\s*\+\s*$', '', text(q))
        out.append({'@type': 'Question', 'name': q, 'acceptedAnswer': {'@type': 'Answer', 'text': text(a)}})
    return out


def offer(name, desc, price=None, unit=None, min_price=None, anchor=''):
    o = {'@type': 'Offer', 'name': name, 'description': desc, 'priceCurrency': 'USD',
         'url': SITE + '/services' + anchor, 'availability': 'https://schema.org/InStock',
         'seller': {'@id': SITE + '/#business'}}
    if price is not None:
        spec = {'@type': 'UnitPriceSpecification', 'price': price, 'priceCurrency': 'USD'}
        if unit: spec['unitCode'], spec['unitText'] = unit
        o['price'] = price
        o['priceSpecification'] = spec
    if min_price is not None:
        o['priceSpecification'] = {'@type': 'PriceSpecification', 'minPrice': min_price, 'priceCurrency': 'USD'}
    return o


def service(name, anchor, desc, offers):
    return {'@type': 'Service', '@id': SITE + '/services' + anchor, 'name': name, 'serviceType': name, 'description': desc,
            'url': SITE + '/services' + anchor, 'provider': {'@id': SITE + '/#business'},
            'areaServed': [{'@type': 'Country', 'name': 'United States'}],
            'offers': offers}


def catalog():
    hour = ('HUR', 'per hour')
    month = ('MON', 'per month')
    items = [
        service('Website Copy', '#website-copy', 'Website copy written in your voice, in three packages by number of pages. Each includes a discovery conversation and two rounds of revisions.', [
            offer('Website Copy — Starter', 'Up to three primary pages, one discovery conversation, two rounds of revisions.', 900, anchor='#website-copy'),
            offer('Website Copy — Standard', 'Up to five pages (three primary, two secondary), two rounds of revisions.', 1350, anchor='#website-copy'),
            offer('Website Copy — Full Site', 'Six or more pages, scoped after a conversation. Two rounds of revisions per page.', min_price=1800, anchor='#website-copy')]),
        service('Email Campaigns', '#email-campaigns', 'Welcome, launch, nurture and re-engagement sequences: subject line, preview text, body and call-to-action copy for every email.', [
            offer('Email Campaign — up to 3 emails', 'Subject line, preview text, body copy and call to action for each email.', 650, anchor='#email-campaigns'),
            offer('Email Campaign — 4–5 emails', 'Subject line, preview text, body copy and call to action for each email.', 950, anchor='#email-campaigns'),
            offer('Email Campaign — 6–7 emails', 'Subject line, preview text, body copy and call to action for each email.', 1250, anchor='#email-campaigns')]),
        service('Editing', '#editing', 'Substantive editing for voice, clarity and impact, returned as a marked-up version with changes and notes. Two-hour minimum.', [
            offer('Editing', 'Substantive editing, $150 per hour with a two-hour minimum.', 150, hour, anchor='#editing')]),
        service('Blog Posts & Articles', '#blog-posts', 'Blog posts of 600–900 words that don’t read like generic content. Longer articles and thought leadership scoped after a conversation.', [
            offer('Blog post (600–900 words)', 'One blog post of 600–900 words.', min_price=300, anchor='#blog-posts')]),
        service('Ghostwriting', '#ghostwriting', 'LinkedIn posts, essays, op-eds and executive thought leadership written in your voice.', [
            offer('Ghostwriting', 'Ghostwriting billed hourly.', 150, hour, anchor='#ghostwriting')]),
        service('Copywriting Retainer', '#retainers', 'A monthly block of writing hours for businesses with ongoing needs, billed at the start of each month.', [
            offer('Retainer — 3 hours a month', 'Light touch: a few small things per month.', 450, month, anchor='#retainers'),
            offer('Retainer — 5 hours a month', 'Moderate: one bigger project plus some small things.', 700, month, anchor='#retainers'),
            offer('Retainer — 8 hours a month', 'Heavy: a significant ongoing writing partnership.', 1050, month, anchor='#retainers')]),
    ]
    return {'@type': 'OfferCatalog', '@id': SITE + '/services#catalog', 'name': 'Copywriting services', 'itemListElement': items}


def graph(key, page_html):
    p = PAGES[key]
    url = SITE + p['path']
    business = {
        '@type': 'ProfessionalService', '@id': SITE + '/#business', 'name': BRAND,
        'alternateName': 'Mike Billeter, Copywriter',
        'description': 'Freelance copywriting, editing and ghostwriting by Mike Billeter: website copy, email campaigns, blog posts and retainers for businesses that want to sound like themselves.',
        'slogan': 'Copy that sounds like somebody wrote it.',
        'url': SITE + '/', 'email': EMAIL, 'image': [HEADSHOT, OG_IMAGE], 'logo': SITE + '/assets/icon-512.png',
        'founder': {'@id': SITE + '/#mike'}, 'employee': {'@id': SITE + '/#mike'},
        'address': {'@type': 'PostalAddress', 'addressLocality': 'Sioux Falls', 'addressRegion': 'SD', 'addressCountry': 'US'},
        'areaServed': [{'@type': 'City', 'name': 'Sioux Falls', 'containedInPlace': {'@type': 'State', 'name': 'South Dakota'}},
                       {'@type': 'State', 'name': 'South Dakota'}, {'@type': 'Country', 'name': 'United States'}],
        'priceRange': '$150–$1,800+', 'currenciesAccepted': 'USD', 'knowsAbout': KNOWS, 'knowsLanguage': 'en',
        'hasOfferCatalog': {'@id': SITE + '/services#catalog'},
        'contactPoint': {'@type': 'ContactPoint', 'contactType': 'sales', 'email': EMAIL, 'url': SITE + '/contact', 'availableLanguage': 'English'},
    }
    person = {
        '@type': 'Person', '@id': SITE + '/#mike', 'name': 'Mike Billeter', 'givenName': 'Mike', 'familyName': 'Billeter',
        'jobTitle': 'Copywriter', 'url': SITE + '/about', 'image': HEADSHOT, 'email': EMAIL,
        'worksFor': {'@id': SITE + '/#business'}, 'knowsAbout': KNOWS,
        'address': {'@type': 'PostalAddress', 'addressLocality': 'Sioux Falls', 'addressRegion': 'SD', 'addressCountry': 'US'},
    }
    website = {'@type': 'WebSite', '@id': SITE + '/#website', 'url': SITE + '/', 'name': BRAND,
               'description': 'Website copy, email campaigns, editing and ghostwriting by Sioux Falls copywriter Mike Billeter.',
               'publisher': {'@id': SITE + '/#business'}, 'inLanguage': 'en-US'}
    webpage = {'@type': p['type'], '@id': url + '#webpage', 'url': url, 'name': p['title'], 'description': p['desc'],
               'isPartOf': {'@id': SITE + '/#website'}, 'about': {'@id': SITE + '/#business'}, 'inLanguage': 'en-US',
               'primaryImageOfPage': {'@type': 'ImageObject', 'url': OG_IMAGE, 'width': 1200, 'height': 630},
               'dateModified': TODAY}
    nodes = [website, business, person, webpage]
    if key != 'index':
        webpage['breadcrumb'] = {'@id': url + '#breadcrumb'}
        nodes.append({'@type': 'BreadcrumbList', '@id': url + '#breadcrumb', 'itemListElement': [
            {'@type': 'ListItem', 'position': 1, 'name': 'Home', 'item': SITE + '/'},
            {'@type': 'ListItem', 'position': 2, 'name': p['crumb'], 'item': url}]})
    if key == 'about':
        webpage['mainEntity'] = {'@id': SITE + '/#mike'}
    if key == 'services':
        nodes.append(catalog())
        webpage['mainEntity'] = {'@id': SITE + '/services#catalog'}
    if key == 'contact':
        qa = faq(page_html)
        if qa:
            nodes.append({'@type': 'FAQPage', '@id': url + '#faq', 'url': url, 'mainEntity': qa, 'isPartOf': {'@id': url + '#webpage'}})
    return {'@context': 'https://schema.org', '@graph': nodes}


def head_block(key, page_html):
    p = PAGES[key]
    url = SITE + p['path']
    e = lambda s: html.escape(s, quote=True)
    og_title = p.get('og_title', p['title'])
    ld = json.dumps(graph(key, page_html), ensure_ascii=False, separators=(',', ':')).replace('</', '<\\/')
    return f'''<!-- seo:start (generated by tools/seo.py) -->
<title>{e(p['title'])}</title>
<meta name="description" content="{e(p['desc'])}">
<link rel="canonical" href="{url}">
<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
<meta name="author" content="Mike Billeter">
<meta name="theme-color" content="#ffffff">
<meta name="geo.region" content="US-SD">
<meta name="geo.placename" content="Sioux Falls">
<meta property="og:type" content="{'profile' if key == 'about' else 'website'}">
<meta property="og:site_name" content="{BRAND}">
<meta property="og:locale" content="en_US">
<meta property="og:url" content="{url}">
<meta property="og:title" content="{e(og_title)}">
<meta property="og:description" content="{e(p['desc'])}">
<meta property="og:image" content="{OG_IMAGE}">
<meta property="og:image:secure_url" content="{OG_IMAGE}">
<meta property="og:image:type" content="image/jpeg">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="Mike Billeter Creative: copy that sounds like somebody wrote it. Freelance copywriter, Sioux Falls, South Dakota.">
{'<meta property="profile:first_name" content="Mike">' + chr(10) + '<meta property="profile:last_name" content="Billeter">' + chr(10) if key == 'about' else ''}<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{e(og_title)}">
<meta name="twitter:description" content="{e(p['desc'])}">
<meta name="twitter:image" content="{OG_IMAGE}">
<meta name="twitter:image:alt" content="Mike Billeter Creative: copy that sounds like somebody wrote it.">
<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
<link rel="icon" href="/assets/icon-32.png" type="image/png" sizes="32x32">
<link rel="apple-touch-icon" href="/assets/apple-touch-icon.png">
<link rel="manifest" href="/site.webmanifest">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&display=swap">
<script type="application/ld+json">{ld}</script>
<!-- seo:end -->'''


def clean_links(s, y2k=False):
    # index.html → / (or ./ inside /y2k/), page.html(#x) → /page(#x) (or page(#x) inside /y2k/)
    def rep(m):
        name, frag = m.group(1), m.group(2) or ''
        if name == 'index':
            return 'href="' + ('./' if y2k else '/') + frag + '"'
        return 'href="' + ('' if y2k else '/') + name + frag + '"'
    s = re.sub(r'href="(index|services|process|about|contact)\.html(#[^"]*)?"', rep, s)
    if y2k:
        s = re.sub(r'href="\.\./(index|services|process|about|contact)\.html(#[^"]*)?"',
                   lambda m: 'href="/' + ('' if m.group(1) == 'index' else m.group(1)) + (m.group(2) or '') + '"', s)
    return s


def modern(key):
    f = key + '.html'
    s = read(f)
    block = head_block(key, s)
    if '<!-- seo:start' in s:
        s = re.sub(r'<!-- seo:start.*?<!-- seo:end -->', lambda m: block, s, flags=re.S)
    else:
        s = re.sub(r'<title>.*?(?=<script)', lambda m: block + '\n', s, count=1, flags=re.S)
    # Drop any JSON-LD outside the generated block (the old single-node markup).
    a, b = s.split('<!-- seo:end -->', 1)
    s = a + '<!-- seo:end -->' + re.sub(r'<script type="application/ld\+json">.*?</script>\n?', '', b, flags=re.S)
    s = s.replace('<html lang="en">', '<html lang="en-US">')
    s = clean_links(s)
    # Root-relative asset paths so pages also work at nested URLs.
    s = re.sub(r'(href|src)="(css|js|assets)/', r'\1="/\2/', s)
    s = re.sub(r'(srcset="[^"]*")', lambda m: re.sub(r'(^|, |")assets/', r'\1/assets/', m.group(1)), s)
    write(f, s)


def y2k(key):
    f = 'y2k/' + key + '.html'
    s = read(f)
    s = clean_links(s, y2k=True)
    s = s.replace('Mike Billeter Writing', BRAND)
    if 'name="robots" content="noindex"' in s:
        s = s.replace('<meta name="robots" content="noindex">', '<meta name="robots" content="noindex, follow">')
    write(f, s)


def sitemap():
    pri = {'index': '1.0', 'services': '0.9', 'contact': '0.8', 'about': '0.7', 'process': '0.6'}
    rows = []
    for k in ['index', 'services', 'process', 'about', 'contact']:
        img = ''
        if k in ('index', 'about'):
            img = (f'\n    <image:image><image:loc>{SITE}/assets/mike-1600.jpg</image:loc></image:image>')
        rows.append(f'  <url>\n    <loc>{SITE}{PAGES[k]["path"]}</loc>\n    <lastmod>{TODAY}</lastmod>\n    <changefreq>monthly</changefreq>\n    <priority>{pri[k]}</priority>{img}\n  </url>')
    write('sitemap.xml', '<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">\n' + '\n'.join(rows) + '\n</urlset>\n')


def robots():
    write('robots.txt', f'''User-agent: *
Allow: /
Disallow: /admin/
Disallow: /forms/

Sitemap: {SITE}/sitemap.xml
''')


def llms():
    write('llms.txt', f'''# {BRAND}

> Mike Billeter is a freelance copywriter based in Sioux Falls, South Dakota, working with businesses across the United States. He writes website copy, email campaigns, blog posts and articles, and ghostwritten pieces, and edits existing copy, with a focus on making businesses sound like themselves instead of like everyone else in their industry. Prices are published.

- Website copy: Starter (up to 3 pages) $900, Standard (up to 5 pages) $1,350, Full Site (6+ pages) from $1,800
- Email campaigns: up to 3 emails $650, 4–5 emails $950, 6–7 emails $1,250
- Blog posts and articles: from $300 per 600–900-word post
- Editing: $150/hour, two-hour minimum
- Ghostwriting: $150/hour
- Monthly retainers: 3 hours $450, 5 hours $700, 8 hours $1,050
- Contact: {EMAIL} or the form at {SITE}/contact. Usually replies within a day or two. Currently accepting new clients.

## Pages

- [Home]({SITE}/): what Mike does and how he thinks about copy
- [Services and pricing]({SITE}/services): every service, what it includes, who it's for, and what it costs
- [How it works]({SITE}/process): the six steps from first email to final delivery
- [About Mike]({SITE}/about): background, clients, and how he approaches the work
- [Contact and FAQs]({SITE}/contact): contact form and answers on timelines, revisions and getting started
''')


if __name__ == '__main__':
    for k in PAGES:
        modern(k)
        y2k(k)
    sitemap(); robots(); llms()
    print('SEO pass complete for', ', '.join(PAGES))
