# 🌟 Atela SEO Premium

> Profesjonalna, zaawansowana wtyczka SEO dla WordPress ze zintegrowanym wsparciem dla Elementora, dynamicznymi podglądami na żywo i automatycznym letterboxingiem obrazów social media.

[![WordPress](https://img.shields.io/badge/WordPress-5.9%2B-blue?logo=wordpress)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php)](https://php.net)
[![Elementor](https://img.shields.io/badge/Elementor-3.0%2B-92003B?logo=elementor)](https://elementor.com)
[![License](https://img.shields.io/badge/License-GPL%20v2-green)](https://www.gnu.org/licenses/gpl-2.0.html)

---

## O wtyczce

**Atela SEO Premium** to autorska wtyczka SEO stworzona z myślą o pełnej kontroli nad optymalizacją stron WordPress. Oferuje dedykowaną zakładkę bezpośrednio w edytorze Elementora, zaawansowane podglądy wyników wyszukiwania (Google, Facebook, Twitter/X) z przełącznikiem Mobile/Desktop, a także potężny menedżer przekierowań, mapy witryny i dane strukturalne Schema.org.

---

## ✅ Zaimplementowane funkcje

### 🔍 SEO on-page
- **SEO Title** z systemem zmiennych (`%title%`, `%site_name%`, `%site_desc%`, `%sep%`, `%page%`)
- **Meta Description** z licznikiem znaków i wizualnym podglądem
- **Canonical URL** (automatyczny + ręczny override)
- **Robots meta** (noindex per-strona i globalnie)
- **Fraza kluczowa** (Focus Keyword) per wpis/strona
- **Separator tytułu** – wybór spośród 9 wariantów (-, –, —, ·, •, |, ~, «, »)

### 🏷️ SEO dla Kategorii i Tagów (Taxonomy SEO)
- Dedykowane pola Meta Title, Meta Description i Noindex dla kategorii, tagów i własnych taksonomii
- Generowanie prawidłowych znaczników w `<head>` dla archiwów
- Wsparcie dla systemu zmiennych

### 🔄 Menedżer Przekierowań (301, 302)
- Błyskawiczne dodawanie przekierowań bezpośrednio w panelu WP
- Obsługa wyrażeń z trailing slash (automatyczna normalizacja)
- Możliwość masowego importu i eksportu z/do pliku CSV
- Statystyki trafień (Hits) dla każdego przekierowania

### 🗺️ XML Sitemaps
- Automatyczne generowanie mapy witryny indeksującej (`sitemap_index.xml`) oraz map dla wpisów, stron i taksonomii (`sitemap.xml`)
- Inteligentne wsparcie dla obrazków w mapie witryny (Sitemap Image)
- Automatyczne pingowanie wyszukiwarek (Google, Bing) po aktualizacji treści
- Zgodność z oficjalnym protokołem Sitemaps.org

### 🧩 Schema.org (JSON-LD)
- Automatyczne wstrzykiwanie danych strukturalnych dla strony głównej (`WebSite`, `Organization`)
- Struktura `Article` dla wpisów blogowych (autor, data publikacji, miniatura)
- Struktura `WebPage` dla stron statycznych

### 🍞 Okruszki (Breadcrumbs)
- W pełni funkcjonalny system nawigacji okruszkowej wspierający struktury WordPressa
- Dedykowany widget w Elementorze pozwalający na dowolne formatowanie i stylizację
- Zgodność ze znacznikami Schema.org (BreadcrumbList)

### 📱 Podglądy Live z przełącznikiem Mobile / Desktop
- **Google SERP** – pełna symulacja wyniku wyszukiwania z faviconem, nazwą witryny, URL i miniaturą zdjęcia (wersja mobilna)
- **Facebook Open Graph** – podgląd udostępnianego linku z obrazem (letterboxing) i tytułem
- **Twitter / X Card** – podgląd karty Twitterowej z obrazem i opisem
- **Globalny Toggle** Mobile ↔ Desktop – jeden przełącznik zmienia widok wszystkich podglądów jednocześnie; preferencja zapamiętywana w przeglądarce (localStorage)
- Liczniki znaków (z kolorową sygnalizacją: 🟢 optymalnie, 🟡 za krótki, 🔴 za długi)

### 🖼️ Automatyczny Letterboxing Obrazów
- Wtyczka automatycznie generuje wersję 1200×630px każdego obrazu OG
- Niepasujące proporcje (np. kwadratowe logo) są centrowane na białym tle zamiast obcinania
- Plik tworzony "w locie" na serwerze (biblioteka GD) i cachowany
- Dziedziczenie: własny obraz OG → obraz z Elementora → Featured Image → globalny fallback

### 🌐 Social Media (Open Graph + Twitter Cards)
- Tagi `og:title`, `og:description`, `og:image`, `og:url`, `og:type`
- Tagi `twitter:card`, `twitter:title`, `twitter:description`, `twitter:image`
- Konfiguracja Twitter @username witryny i typ karty (Summary / Summary Large Image)
- Globalny obraz OG fallback (media picker w ustawieniach)

### ⚡ Integracja z Elementorem
- **Dedykowana zakładka "🌟 Atela SEO Premium"** w panelu Ustawień Strony Elementora
- Pola SEO Title, Meta Description, Noindex, OG Image bezpośrednio w edytorze wizualnym
- Zapis przez natywny mechanizm Elementora (opublikuj/aktualizuj)

### 🧠 Integracja z Gutenberg / Classic Editor
- **Meta Box "Atela SEO Premium"** pod edytorem z pełnym zestawem pól SEO i Social
- Podglądy aktualizowane na żywo podczas wpisywania (nasłuch na `wp.data.subscribe`)
- Automatyczne pobieranie tytułu z Gutenberga i Classic Editora
- Parser zmiennych (`%title%`, `%sep%`, itp.) działający w czasie rzeczywistym w JS

### 🛠️ Panel Ustawień Głównych
- Zakładka **Ogólne** – globalny noindex, podstawowe ustawienia
- Zakładka **Wygląd w Wyszukiwarce** – separator, tytuł i opis strony głównej + podgląd SERP Live
- Zakładka **Social Media** – domyślny obraz OG, Twitter username, typ karty + podglądy FB/X Live
- Zakładka **Sitemap** – kontrola nad włączaniem i wyłączaniem map witryny
- Zakładka **Schema.org** – konfiguracja danych organizacji (logo, telefon, social media)
- **Menedżer Przekierowań** – pełny panel zarządzania ruchem (301/302)

---

## 🚧 Roadmap (planowany rozwój)

| Moduł | Status |
|---|---|
| Integracja z Google Search Console i Indexing API | 🔜 Następny |
| Analiza treści w czasie rzeczywistym (system 🔴🟡🟢) | 🔜 W kolejce |
| Analiza czytelności (długość zdań, akapitów, nagłówki) | 🔜 W kolejce |
| Edytor robots.txt | 🔜 W kolejce |
| Auto 301 (automatyczne przekierowania po zmianie slug'a) | 🔜 W kolejce |

---

## 📋 Wymagania

| Komponent | Minimalna wersja |
|---|---|
| WordPress | 5.9+ |
| PHP | 7.4+ |
| GD Library | wymagana (dla letterboxingu obrazów) |
| Elementor (Free) | 3.0+ (opcjonalnie) |

> Elementor **nie jest wymagany** do działania wtyczki. Jest potrzebny wyłącznie do korzystania z dedykowanej zakładki SEO w edytorze wizualnym.

---

## 🚀 Instalacja

### Metoda 1: Ręczna (z pliku ZIP)
1. Pobierz repozytorium jako ZIP (`Code → Download ZIP`)
2. W panelu WordPress przejdź do **Wtyczki → Dodaj nową → Wyślij wtyczkę**
3. Wybierz pobrany plik ZIP i kliknij **Zainstaluj teraz**
4. Aktywuj wtyczkę

### Metoda 2: Przez FTP/SSH
```bash
cd /wp-content/plugins/
git clone https://github.com/BasiaNiedbal/atela-seo.git
```
Następnie aktywuj wtyczkę w panelu WordPress.

---

## 📁 Struktura projektu

```
atela-seo/
├── atela-seo.php                    # Główny plik wtyczki
├── includes/
│   ├── class-atela-seo-autoloader.php       # Autoloader klas
│   ├── class-atela-seo-core.php             # Główna klasa wtyczki
│   ├── admin/
│   │   ├── class-atela-seo-admin.php        # Panel WP Admin + meta box + podglądy
│   │   ├── class-atela-seo-redirects.php    # Menedżer Przekierowań (Admin)
│   │   └── class-atela-seo-taxonomy.php     # SEO dla Kategorii i Tagów
│   ├── frontend/
│   │   ├── class-atela-seo-frontend.php     # Renderowanie tagów w <head>
│   │   ├── class-atela-seo-breadcrumbs.php  # Generator struktury Okruszków
│   │   ├── class-atela-seo-sitemap.php      # Generator XML Sitemap
│   │   └── class-atela-seo-schema.php       # Dane strukturalne JSON-LD
│   ├── social/
│   │   └── class-atela-seo-social.php       # Open Graph + Twitter Cards
│   └── integrations/
│       ├── class-atela-seo-elementor.php    # Integracja z zakładkami Elementora
│       └── class-atela-seo-breadcrumbs-widget.php # Widget Elementora dla Okruszków
├── assets/
│   ├── css/
│   │   ├── admin-preview.css                # Style podglądów (Mobile/Desktop)
│   │   ├── admin-style.css                  # Style panelu ustawień
│   │   ├── atela-seo-editor.css             # Style zakładki Elementora
│   │   └── breadcrumbs.css                  # Style nawigacji okruszkowej
│   └── js/
│       ├── admin-preview.js                 # Logika podglądów Live + Toggle
│       ├── atela-seo-editor.js              # Logika panelu Elementora
│       └── content-analysis.js              # Analiza treści (w rozwoju)
└── README.md
```

---

## 🏗️ Architektura

- **Autoloader** – automatyczne ładowanie klas przez `spl_autoload_register` z przeszukiwaniem podfolderów
- **Klasa Core** – zarządza cyklem życia wtyczki, inicjalizacją modułów
- **Separacja odpowiedzialności** – oddzielne klasy dla Admin, Frontend, Social, Elementor, Przekierowań, Sitemap
- **Mobile-first CSS** – klasy `.is-mobile` / `.is-desktop` sterowane z JS dla responsywnych podglądów
- **Hooki Elementora** – `elementor/init`, `elementor/documents/register_controls`, `elementor/document/after_save`
- **Gutenberg Redux** – nasłuch na `wp.data.subscribe` dla real-time aktualizacji podglądów

---

## 🔐 Bezpieczeństwo

- Sanityzacja danych wejściowych (`sanitize_text_field`, `sanitize_textarea_field`)
- Escapowanie danych wyjściowych (`esc_attr`, `esc_textarea`, `esc_url`, `esc_html`)
- Ochrona formularzy przez WordPress Nonce
- Weryfikacja uprawnień (`current_user_can`)
- Blokada bezpośredniego dostępu do plików PHP (`if ( ! defined( 'ABSPATH' ) ) exit;`)

---

## 👤 Autor

**Atela** – [atela.pl](https://atela.pl)

---

## 📄 Licencja

[GPL v2 lub nowsza](https://www.gnu.org/licenses/gpl-2.0.html) — zgodnie z wymaganiami ekosystemu WordPress.
