# Cadenza — specyfikacja projektu

> *Cadenza* — w koncercie fragment, w którym solista swobodnie improwizuje w ramach utworu. Projekt na **Symfony AI & MCP Hackathon** (26.10–22.11.2026, oddanie do **22.11.2026, 18:00 CET**).
> Wersja dokumentu: 0.1 (28.10.2026). Dokument roboczy po polsku; README i film dla jury — po angielsku.

---

## 1. Jednym zdaniem

**Cadenza to żywa aplikacja Symfony, którą nietechniczny użytkownik kształtuje rozmową:** wrzuca swój materiał (Excel,
zdjęcia, dokumenty) albo opisuje wizję, agent pokazuje szkic zmian bezpośrednio na stronie, użytkownik zatwierdza, a
zmiana materializuje się na jego oczach — z biblioteki klocków full-stack, którą agent sam umie rozbudowywać.

## 2. Po co to jest i dlaczego wygra

### 2.1 Problem
Ludzie mają materiał i wizję, ale nie mają programisty. Kreatory (Airtable, AppSheet, Lovable, Bolt) albo zamykają
w swojej chmurze, albo generują kod, którego nikt potem nie utrzyma. Panele admina są dla techników.

### 2.2 Odpowiedź
- **Strona jest jednocześnie edytorem.** Nie ma panelu admina: użytkownik jest na swojej stronie, otwiera boczny czat,
  wskazuje element palcem i mówi, co zmienić.
- **Najpierw szkic, potem budowa.** Każda zmiana jest najpierw pokazana jako szkic na żywej stronie; nic nie dzieje się
  bez akceptacji.
- **Prawdziwa aplikacja Symfony**, którą użytkownik posiada: git, migracje, testy, kod do otwarcia w PhpStorm.
- **Klocki full-stack + szyna zdarzeń** — wszystko jest plug & play; brakujący klocek agent tworzy sam, sprawdza go na
  prawdziwych zdarzeniach w trybie cienia i dokłada do biblioteki.
- **Aplikacja sama się leczy** — nieudany komunikat Messengera → diagnoza (Mate) → poprawka → ponowienie.

### 2.3 Mapowanie na kryteria jury

| Kryterium | Jak Cadenza na nie odpowiada |
|---|---|
| **Creative idea** | Nie chatbot: rozmowa *zmienia* aplikację, w której toczy się rozmowa; szkic na żywej stronie; aplikacja, która się leczy i rośnie. |
| **It actually works** | `git clone` → `docker compose up` / `symfony serve` → w 5 minut juror wrzuca **własny** Excel albo zdjęcia. Działa na HF (kupon), na abonamencie Claude Code/Codex albo na modelu lokalnym. |
| **What it unlocked** | Agent Symfony AI z narzędziami MCP, które operują na **drzewie strony i szynie zdarzeń**, Mate jako „stetoskop” samonaprawy, MCP Apps jako drugi interfejs, StructuredOutput jako kontrakt zmian, Toolkit jako format biblioteki klocków. Cały ekosystem Symfony jako zestaw klocków dla agenta. |
| **Craft** | Idiomatyczny Symfony, `declare(strict_types=1)`, PHPStan max, testy kontraktów klocków i operacji na drzewie, README na 5 minut, issue/PR do `symfony/ai` i `php-sdk`. |

---

## 3. Doświadczenie użytkownika (najważniejszy rozdział)

Zasada nadrzędna: **użytkownik nigdy nie opuszcza swojej strony.** Wszystko — rozmowa, szkic, akceptacja, budowa,
cofnięcie — dzieje się na stronie, którą właśnie ogląda.

### 3.1 Tryby
- **Tryb gościa** — opublikowana strona, zero śladów edytora.
- **Tryb właściciela** — ta sama strona + pasek Cadenzaa. Przełączenie bez przeładowania.
- **Tryb szkicu** — strona pokazuje proponowaną wersję (patrz 3.4); w każdej chwili można wrócić do bieżącej.

### 3.2 Pasek i panel czatu
- **Pasek** (zwinięty stan): wąski, przyklejony do dołu ekranu (na telefonie — pływający przycisk). Zawiera:
  pole „Powiedz, co zmienić…”, przycisk **wskaż element** (celownik), status agenta (bezczynny / myśli / buduje /
  czeka na Ciebie) i licznik propozycji do akceptacji.
- **Panel** wysuwa się z boku (shadcn `Sheet`), można go zwinąć do paska albo całkiem ukryć (skrót `Cmd/Ctrl+K`
  przywraca — shadcn `Command`). Panel **przeżywa nawigację** (`data-turbo-permanent`), więc rozmowa trwa, gdy agent
  albo użytkownik zmienia podstronę.
- Zawartość panelu (komponenty z UX Toolkit, kit shadcn):
  - `MessageScroller` + `Message`/`Bubble` — rozmowa ze strumieniowaniem odpowiedzi,
  - `Trace` — co agent robi: rozumowanie, wywołania narzędzi, bramki (zwinięte domyślnie, dla ciekawskich),
  - `Questionnaire` — pytania doprecyzowujące z wyborem („Status: workflow czy lista?”),
  - `Attachment` — wrzucanie plików (Excel, zdjęcia, PDF) z postępem,
  - karta **Propozycji** z przyciskami *Pokaż szkic / Zatwierdź / Popraw / Odrzuć*.

### 3.3 Wskazywanie elementów
Najprostszy sposób dla nietechnicznego użytkownika: **„to”**.
- Po kliknięciu celownika strona dostaje nakładkę: najechanie podświetla węzeł drzewa strony (sekcję, komponent),
  klik dodaje go do kontekstu rozmowy jako „chip” (`Hero › Nagłówek`). Można wskazać kilka.
- Wiadomość „zrób to większe i daj tu zdjęcie z gór” trafia do agenta z identyfikatorami wskazanych węzłów —
  agent nie zgaduje, o który element chodzi.

### 3.4 Szkic — „pokaż, jak to może wyglądać”
Każda zmiana zaczyna się od **Propozycji** (zestawu operacji na drzewie, patrz §5). Zanim cokolwiek zostanie zbudowane:
1. Agent może **przejść na właściwą podstronę** (zmienić URL — nawigacja Turbo sterowana z serwera) i **przewinąć /
   podświetlić** miejsce zmiany. Użytkownik widzi „wskaźnik agenta” przesuwający się po stronie.
2. Strona przechodzi w **tryb szkicu**: proponowana wersja renderuje się z prawdziwymi komponentami tam, gdzie się da,
   a tam, gdzie czegoś jeszcze nie ma (nowy klocek, brak danych), pojawia się **stub** — szkielet (shadcn `Skeleton`)
   z etykietą, czym będzie („Galeria · 3 albumy · mapa”).
3. Różnice są zaznaczone: dodane — zielony obrys, usunięte — wyblakłe i przekreślone, zmienione — pulsujący obrys.
   Przełącznik **Przed / Po**.
4. Użytkownik: **Zatwierdź**, **Popraw** (dalsza rozmowa modyfikuje tę samą propozycję) albo **Odrzuć**.
5. Po zatwierdzeniu szkic **przechodzi w gotową stronę z animacją** (View Transitions API, które Turbo obsługuje
   natywnie): stuby „wypełniają się” w miarę, jak klocki kończą budowę, bez przeładowania strony.

Szkic jest tani (milisekundy), bo to tylko inne drzewo strony renderowane tym samym silnikiem. Dzięki temu agent może
pokazać 2–3 warianty („klasycznie / magazynowo / minimalistycznie”) do wyboru.

### 3.5 Budowa na oczach użytkownika
- Zmiany **prezentacyjne** (układ, treść, motyw) stosują się natychmiast po akceptacji.
- Zmiany wymagające **nowych możliwości** (nowy klocek, nowa encja, import danych) dzieją się w tle: stub pokazuje
  postęp (instalacja klocka → migracja → import 340/1200 wierszy → gotowe), a dane wjeżdżają na żywo (Mercure +
  Turbo Streams / Live Components).
- Panel pokazuje przebieg bramek w `Trace`; użytkownik może przeglądać inne strony w trakcie.

### 3.6 Historia i cofanie
- Każda zatwierdzona propozycja to wpis na **osi czasu** („Dodano galerię”, „Zmieniono motyw na ciemny”).
- **Cofnij** jednym kliknięciem (odwrócenie operacji na drzewie; dla zmian kodu — rollback wersji, patrz §7.4).
- Podgląd dowolnego punktu historii w trybie szkicu.

### 3.7 Swoboda wizji
Użytkownik ma kształtować stronę „po swojemu”, nie wybierać z trzech szablonów. Swobodę zapewniają warstwy (§4):
- **motyw** (kolory, fonty, zaokrąglenia, gęstość, tryb ciemny) — „ma być jak w kawiarni, ciepło i drewniano”,
- **układ** dowolnie składany z prymitywów (sekcje, kolumny, siatki, warstwy),
- **treść i media** — tekst, zdjęcia, ikony, wszędzie edytowalne rozmową,
- **dane i zachowanie** — klocki (galeria, katalog, rezerwacje, formularze, workflow…),
- **furtka**: gdy niczego nie ma w bibliotece, agent tworzy nowy komponent lub klocek (§6.4).

### 3.8 Zasada „bez sufitu”
Cadenza nie ma listy dozwolonych typów stron. Każdy pomysł użytkownika agent **rozkłada na możliwości**:
*skąd przychodzą dane* (źródła), *co się z nimi dzieje* (klocki przetwarzające), *jak się je ogląda i jak się z nimi
wchodzi w interakcję* (widoki i komponenty). Brakujący element nie kończy rozmowy — staje się propozycją nowego
klocka (§6.4). Przykłady, które muszą „po prostu działać” rozmową:

| Wizja użytkownika | Rozkład na możliwości |
|---|---|
| „Mam arkusz zamówień, zrób z tego system” | SpreadsheetImport → Records → Approval → Dashboard |
| „Galeria moich zdjęć z wyjazdów” | Gallery → VisionTagger → MapView → SemanticSearch |
| „Przenieś moje zdjęcia z Instagrama na moją stronę” | źródło **Instagram** (eksport danych) → Gallery (+ opisy, daty, hashtagi jako tagi) → strona portfolio |
| „Zaciągnij moje filmy z YouTube / wpisy z bloga (RSS) / zdjęcia z Google Photos” | odpowiednie **źródło** → Records/Gallery → widok |
| „Chcę stronę, która wytłumaczy mi fotosyntezę / podatki / jak działa silnik” | **Explainer**: plan nauki → lekcje → ilustracje i diagramy → quizy i fiszki → „zapytaj tę stronę” |
| „Strona mojej kawiarni z menu i rezerwacją stolika” | Pages → Records (menu) → Booking → PublicForm → Reminders |

Dla wizji, których nikt nie przewidział, ta sama ścieżka: rozkład → reuse z biblioteki → nowe klocki za bramkami.

---

## 4. Hierarchia strony — model, na którym pracuje agent

Kluczowa decyzja projektu: **agent nie edytuje szablonów Twig ani HTML strony.** Strona jest opisana deklaratywnym
**drzewem** z typowanymi węzłami i stabilnymi identyfikatorami. Agent wykonuje na nim małe, walidowane operacje
(§5). Podmiana elementu to jedna operacja `replace` na jednym identyfikatorze — nie przepisywanie pliku.

### 4.1 Poziomy

```
Site                       motyw (tokeny), nawigacja, stopka, języki, ustawienia
└── Page                   trasa (/galeria/{album}), tytuł, SEO, dostęp, wybrany layout
    └── Region             sloty layoutu: header · main · aside · footer · overlay
        └── Section        prymityw układu: Stack · Columns · Grid · Split · Hero · Band
            └── Node       komponent: UI (Toolkit: Card, Table, Tabs…) albo widok klocka (Gallery.Grid, Map.View…)
                └── Node   (dowolne zagnieżdżanie tam, gdzie komponent ma sloty)
```

- **Site** — jeden na aplikację; **motyw** to tokeny (zmienne CSS w stylu shadcn: `--primary`, `--radius`,
  `--font-heading`…), więc „zrób bardziej elegancko” zmienia tokeny, a nie setki elementów.
- **Page** — trasa Symfony (generowana dynamicznie z drzewa), może mieć parametry wiązane z danymi
  (`/galeria/{album}` → `album` z klocka Galeria).
- **Region** — definiowany przez layout; layouty też są węzłami drzewa (agent może dodać `aside` do layoutu).
- **Section / Node** — komponenty z **katalogiem props** (typ, wartości dozwolone, domyślne), wyprowadzonym z Twig
  Components i manifestów klocków. Agent wie, co wolno ustawić.

### 4.2 Węzeł

```json
{
  "id": "01JC9Z…",                    // ULID, stały przez całe życie węzła
  "type": "gallery.grid",            // komponent z katalogu (UI albo widok klocka)
  "props": { "columns": 3, "aspect": "4/3", "caption": "below" },
  "bind":  { "items": "gallery.photos(album: $route.album, sort: 'takenAt')" },
  "slots": { "empty": [ { "id": "…", "type": "ui.empty", "props": { "title": "Brak zdjęć" } } ] },
  "style": { "padding": "lg", "width": "wide" },   // tylko tokeny, nie dowolny CSS
  "meta":  { "label": "Zdjęcia albumu", "createdBy": "proposal:01JC…" }
}
```

- `props` — walidowane schematem komponentu.
- `bind` — **wiązanie danych** do zapytań udostępnianych przez klocki (każdy klocek deklaruje w manifeście swoje
  źródła danych z parametrami). Wyrażenia — Symfony **ExpressionLanguage** (bezpieczne, bez dowolnego PHP).
- `style` — wyłącznie tokeny projektu (odstępy, szerokość, wariant tła), co gwarantuje spójność.
- `meta.label` — ludzka nazwa używana w czacie i na osi czasu.

### 4.3 Renderowanie
- Jeden generyczny kontroler Symfony renderuje drzewo przez **Twig Components**; każdy węzeł w DOM ma
  `data-node-id` — z tego korzysta celownik, podświetlanie i animacje.
- Drzewo jest przechowywane w bazie aplikacji jako **wersjonowany dokument** (każda wersja = wynik zastosowania
  operacji); opublikowana wersja jest cache'owana.
- Szkic = render drzewa z nałożoną propozycją; znaczniki różnic dodaje renderer.
- Ten sam węzeł może się wyrenderować jako **MCP App** (§8.3) — strona i rozmowa w Claude/ChatGPT używają tych samych
  komponentów.

### 4.4 Dlaczego tak
- **Agentowi jest łatwo**: zna drzewo (narzędzie `page.tree` zwraca zwięzły zarys), adresuje węzły po ID, operacje
  są małe i typowane (StructuredOutput) — wystarcza nawet mniejszy model.
- **Użytkownikowi jest bezpiecznie**: każda operacja jest walidowana, odwracalna i widoczna w szkicu.
- **Kod pojawia się tylko tam, gdzie musi** — w klockach (§6), za bramkami (§7).

---

## 5. Propozycje i operacje

### 5.1 Operacje na drzewie (kontrakt agent → Cadenza)

| Operacja | Znaczenie |
|---|---|
| `page.create` / `page.update` / `page.delete` | strona, trasa, tytuł, dostęp, layout |
| `node.insert(parent, slot, position, node)` | wstaw węzeł |
| `node.replace(id, node)` | podmień węzeł (zachowuje miejsce w drzewie) |
| `node.update(id, props/bind/style)` | zmień część węzła (merge) |
| `node.move(id, parent, slot, position)` | przenieś |
| `node.remove(id)` | usuń |
| `node.wrap(ids, section)` / `node.unwrap(id)` | opakuj w sekcję / rozpakuj |
| `theme.update(tokens)` | zmień tokeny motywu |
| `nav.update(items)` | nawigacja |
| `content.set(id, field, value)` | treść (tekst, obraz) |
| `block.require(type, config)` | wymagaj klocka z biblioteki (instalacja w tle) |
| `block.create(spec)` | zleć utworzenie nowego klocka (§6.4) |
| `data.import(source, mapping)` | import materiału (Excel, zdjęcia…) do encji klocka |

Każda operacja jest DTO PHP (StructuredOutput), walidowana Validatorem i katalogiem komponentów, a jej skutek jest
**odwracalny** (operacja odwrotna liczona przy zastosowaniu).

### 5.2 Cykl życia propozycji (Symfony **Workflow**)

```
drafted ──► sketched ──► approved ──► building ──► gated ──► applied
   ▲            │            │            │           │
   └─ revised ◄─┘            │            └─► failed ─┴─► rolled_back
                rejected ◄───┘
```
- `drafted` — agent złożył operacje; `sketched` — szkic gotowy do obejrzenia;
- `approved` — decyzja użytkownika (tylko człowiek może wykonać to przejście — voter);
- `building` — klocki/migracje/importy w tle; `gated` — bramki przeszły; `applied` — nowa wersja opublikowana;
- przejście `approved` jest **jedynym** miejscem, w którym zmiana staje się trwała — zabezpieczenie wymuszone kodem.

### 5.3 Polecenia interfejsu (agent → przeglądarka)
Agent „prowadzi” użytkownika przez narzędzia emitujące komunikaty do przeglądarki (Mercure):
`ui.navigate(url)`, `ui.highlight(nodeIds)`, `ui.scrollTo(nodeId)`, `ui.showSketch(proposalId, variant)`,
`ui.compare(before|after)`, `ui.ask(questionnaire)`, `ui.toast(message)`. Przeglądarka wykonuje je przez Turbo i
kontrolery Stimulus; użytkownik może przerwać każde (agent nie przejmuje sterowania na siłę).

---

## 6. Klocki i biblioteka

### 6.1 Czym jest klocek
**Klocek = funkcja biznesowa full-stack**: encje + handlery komunikatów + widoki (Twig Components) + źródła danych +
migracje + testy. Klocki **nigdy nie wołają się bezpośrednio** — rozmawiają przez szynę zdarzeń (§7.1), więc są
plug & play: dołożenie lub wyjęcie klocka niczego nie psuje.

### 6.2 Manifest (rozszerzenie formatu przepisów UX Toolkit)
Format świadomie rozszerza `schema-kit-recipe-v1.json` z Symfony UX Toolkit (`type`, `name`, `copy-files`,
`dependencies.{recipe,composer,npm,importmap}`), dodając część backendową:

```json
{
  "$schema": "../../schema-block-v1.json",
  "type": "block",
  "name": "WeatherEnricher",
  "version": "0.1.0",
  "description": "Adds the weather of the day and place a photo was taken.",
  "copy-files": { "src/": "%BLOCKS_DIR%/WeatherEnricher/", "templates/": "%COMPONENTS_DIR%/WeatherEnricher/" },
  "dependencies": { "recipe": ["Badge"], "composer": ["symfony/ai-open-meteo-tool:^1.0@beta"] },
  "messages": {
    "consumes": ["Gallery\\PhotoLocated"],
    "emits":    ["WeatherEnricher\\PhotoWeatherResolved"]
  },
  "data": { "photoWeather": { "params": { "photo": "uid" }, "returns": "WeatherEnricher\\Weather" } },
  "components": { "weather.badge": { "props": { "photo": "uid", "variant": ["compact", "full"] } } },
  "config": { "units": { "enum": ["metric", "imperial"], "default": "metric" } },
  "permissions": { "http": ["api.open-meteo.com"] },
  "status": "candidate"
}
```

- `messages` — kontrakt na szynie; walidowany statycznie (handlery w kodzie muszą zgadzać się z manifestem).
- `data` — źródła danych dostępne dla `bind` w drzewie strony.
- `components` — widoki z katalogiem props (dla agenta i walidacji drzewa).
- `permissions` — co klocek może (hosty HTTP, pliki, maile); egzekwowane middleware'em i konfiguracją HttpClient.
- `status` — `candidate` → `stable` (po N udanych użyciach i przeglądzie).

Instalacja kopiuje pliki do aplikacji (jak `ux:install`) — **kod należy do aplikacji**.

### 6.3 Biblioteka startowa (cel hackathonu: 8 klocków dopracowanych do końca)

| Klocek | Co daje | Główne części Symfony |
|---|---|---|
| **Records** | encje z materiału, listy, filtry, formularze, wyszukiwarka | Doctrine, Form, Validator, UX Autocomplete, Pagination, Table |
| **SpreadsheetImport** | Excel/CSV → schemat → import w tle, raport błędów | PhpSpreadsheet, Messenger, Validator, ExpressionLanguage (formuły) |
| **Gallery** | upload, miniatury, albumy, EXIF | Dropzone, Image, Cropper, Filesystem, Messenger |
| **VisionTagger** | opisy i tagi zdjęć, propozycje albumów | Symfony AI Platform (vision), StructuredOutput |
| **SemanticSearch** | „zdjęcia z psem na plaży”, wyszukiwanie w rekordach | Symfony AI Store (Sqlite/Doctrine), embeddings |
| **MapView** | mapa rekordów/zdjęć z lokalizacją | UX Map |
| **Approval** | workflow z zatwierdzaniem i powiadomieniami | Workflow, Security (votery), Notifier, Mailer |
| **Dashboard** | kafle i wykresy na żywo | Chart.js, Live Components, Mercure |

Stretch: **Reminders** (Scheduler + Mailer), **PublicForm** (formularz dla gości + RateLimiter), **Pages/Blog**
(treści), **Booking** (kalendarz, shadcn `Calendar`/`DatePicker`).

### 6.3a Źródła (konektory) — klocki, które przynoszą dane z zewnątrz
Źródło to zwykły klocek, który **tylko emituje** zdarzenia (`ItemImported`, `MediaImported`) w ujednoliconym
formacie; reszta aplikacji nie wie, skąd dane przyszły. Każde źródło ma tryb jednorazowego importu i (opcjonalnie)
synchronizacji cyklicznej (Scheduler).

| Źródło | Sposób | Uwagi |
|---|---|---|
| **Instagram** | plik eksportu danych (ZIP z „Pobierz swoje informacje”): zdjęcia, filmy, opisy, daty, lokalizacje | API Instagrama wymaga konta firmowego i aplikacji Meta — eksport działa dla każdego i nie łamie regulaminu; API jako opcja |
| **Google Photos / Takeout** | archiwum Takeout (zdjęcia + JSON z metadanymi) | ten sam analizator zdjęć co Gallery |
| **YouTube** | publiczny kanał/playlista | bridge YouTube z Symfony AI Agent (transkrypcje → treść i wyszukiwanie) |
| **RSS / blog** | URL kanału | cykliczna synchronizacja |
| **Strona WWW** | URL | bridge Scraper / Firecrawl z Symfony AI; tylko treści użytkownika lub dozwolone |
| **Pliki** | Excel/CSV, zdjęcia, PDF, Markdown | analizatory materiału (§7.6) |

Zasada: importujemy **dane użytkownika** (jego eksporty, jego kanały). Agent pyta o prawa do treści, gdy źródło nie
jest oczywiście własne.

### 6.3b Explainer — strona, która uczy
Osobna rodzina klocków dla wizji „wytłumacz mi X” — strona jest interaktywnym nauczycielem, nie artykułem:
- **LearningPath** — agent rozmawia o poziomie i celu („matura”, „dla dziecka”, „do pracy”) i proponuje plan lekcji
  jako drzewo stron (szkic całego kursu do akceptacji),
- **Lesson** — treść z sekcjami, przykładami, ilustracjami (generowanie obrazów przez HF TextToImage albo zdjęcia
  użytkownika) i **źródłami** (bridge'e Wikipedia / Tavily / Brave z Symfony AI; każde twierdzenie z linkiem),
- **Diagram** — schematy i osie czasu (Mermaid w Twig Component),
- **Quiz** i **Flashcards** — sprawdzanie wiedzy (shadcn `Questionnaire`), z powtórkami w odstępach (Scheduler),
- **AskThisPage** — czat dla odwiedzających odpowiadający tylko na podstawie treści strony (Store + retriever;
  „nie wiem”, gdy strona tego nie omawia),
- **Interactive** — gdy temat tego wymaga (np. symulacja procentu składanego), agent tworzy nowy interaktywny
  komponent (Live Component z suwakami) ścieżką §6.4.

Postęp ucznia to zdarzenia (`LessonCompleted`, `QuizAnswered`), więc Dashboard i Reminders działają bez zmian.

### 6.4 Tworzenie nowego klocka przez agenta
1. Agent przeszukuje bibliotekę (Store, wyszukiwanie semantyczne po opisach i kontraktach) — **najpierw reuse**.
2. Brak dopasowania → agent projektuje **kontrakt** (manifest: co konsumuje, co emituje, jakie dane i widoki) i
   pokazuje go użytkownikowi w języku nietechnicznym („Za każdym razem, gdy zdjęcie ma lokalizację, sprawdzę pogodę
   z tego dnia i pokażę ją przy zdjęciu”).
3. Generowanie: szkielet z generatorów (MakerBundle + nasze makery klocków), uzupełnianie przez model.
4. **Bramki** (§7.3), w tym **tryb cienia**: nowy klocek dostaje zdarzenia z dziennika (historyczne `PhotoLocated`)
   bez skutków ubocznych; sprawdzamy, czy emituje dokładnie to, co obiecał manifest.
5. Akceptacja użytkownika → klocek trafia do biblioteki jako `candidate` i do aplikacji.

To samo dotyczy **komponentów UI**: gdy żaden komponent Toolkitu nie pasuje do wizji, agent tworzy nowy Twig Component
z katalogiem props (szybsza ścieżka: bez backendu, ale z testem renderu i lintem).

---

## 7. Architektura techniczna

### 7.1 Event-driven: Messenger jako kręgosłup
- **Szyny**: `command.bus` (intencje: `ImportSpreadsheet`, `ApplyProposal`), `event.bus` (fakty:
  `PhotoUploaded`, `RecordCreated`, `ProposalApproved`), `query.bus` (źródła danych dla `bind`).
- **Koperta** każdego komunikatu: `messageId` (ULID), `correlationId`, `causationId`, `blockName`, `occurredAt`.
- **Dziennik zdarzeń** (transport/tabela append-only) — podstawa trybu cienia, osi czasu i debugowania.
- **Middleware**: audyt, uprawnienia klocka (manifest `permissions`), budżet tokenów (RateLimiter), korelacja.
- **Failure transport** = wyzwalacz samonaprawy (§7.5).
- **Harness też jest zdarzeniowy**: `ProposalDrafted`, `SketchRendered`, `ProposalApproved`, `BlockGenerated`,
  `GatePassed/GateFailed`, `ChangeApplied`, `ChangeRolledBack`, `HealingStarted`… — panel `Trace` i oś czasu to
  po prostu widok na te zdarzenia.

### 7.2 Dwa procesy: harness i aplikacja
„Chirurg nie operuje własnego mózgu.”

```
┌───────────────────────── Harness (Cadenza Studio) ─────────────────────────┐
│  Agent (Symfony AI)  ◄──►  Narzędzia (Toolbox + MCP)                         │
│  Analizatory materiału · Biblioteka klocków (Store) · Generator · Bramki     │
│  Proposal Workflow · Dziennik zdarzeń harnessu · Serwer MCP harnessu          │
│  Konsola ratunkowa (działa nawet, gdy aplikacja leży)                         │
└───────────────▲───────────────────────────────┬─────────────────────────────┘
                │ MCP (klient) + Mate (CLI)      │ git worktree → bramki → przełączenie
┌───────────────┴───────────────────────────────▼─────────────────────────────┐
│  Aplikacja użytkownika (zwykły projekt Symfony, własne repo git)             │
│  Cadenza Runtime Bundle: renderer drzewa, pasek i panel czatu, celownik,      │
│     tryb szkicu, polecenia UI (Mercure), serwer MCP introspekcji              │
│  Klocki (skopiowany kod) · Doctrine · Messenger · Mercure                      │
└──────────────────────────────────────────────────────────────────────────────┘
```

- **Harness** nigdy nie jest modyfikowany przez agenta. Ma własną konsolę ratunkową (osobny port).
- **Runtime Bundle** w aplikacji jest stabilny (nie generowany): renderer, panel, celownik, szkic. Panel rozmawia z
  harnessem (HTTP + Mercure).
- **Zmiany drzewa** (prezentacja) — bezpośrednio przez API harness → aplikacja, natychmiast, wersjonowane w bazie.
- **Zmiany kodu** (klocki, encje) — w **git worktree**, przez bramki, potem atomowe przełączenie (symlink jak w
  Deployerze) i `cache:clear`. PHP shared-nothing: następne żądanie widzi nową wersję bez restartu serwera.

### 7.3 Bramki dla zmian kodu
1. walidacja manifestu i kontraktu komunikatów,
2. PHPStan (poziom max dla kodu klocków),
3. testy klocka (PHPUnit) + test renderu komponentów,
4. migracja na kopii bazy (`doctrine:migrations:migrate` na snapshot),
5. tryb cienia (dla klocków konsumujących zdarzenia),
6. test dymny HTTP stron, których dotyczy zmiana,
7. commit w repo aplikacji z opisem propozycji.

Komendy uruchamia **Process** z białej listy; jedna zmiana naraz (**Lock**).

### 7.4 Wersje i rollback
- Drzewo strony: każda wersja w bazie, cofnięcie = zastosowanie operacji odwrotnych.
- Kod: commit na propozycję; rollback = przełączenie symlinka na poprzednie wydanie + migracja w dół.
- Oś czasu łączy oba rodzaje w jedną historię dla użytkownika.

### 7.5 Samonaprawa
1. Komunikat ląduje w failure transport albo pojawia się wyjątek przy renderze.
2. Harness zbiera kontekst przez **Mate**: `monolog-search`, `symfony-profiler-get`, `symfony-services` (działa nawet,
   gdy aplikacja się nie uruchamia).
3. Najpierw najtańsze: czy błąd wynika z ostatniej zmiany? → propozycja cofnięcia.
4. W przeciwnym razie agent pisze test odtwarzający błąd i poprawkę → bramki → **propozycja naprawy** dla użytkownika
   (w języku ludzkim: „Import padł na wierszu 214, bo data była w formacie 03.11.24. Poprawiłem rozpoznawanie dat.”).
5. Po akceptacji: wdrożenie i **ponowienie nieudanych komunikatów** (`messenger:failed:retry`) — widać, jak to, co
   się wysypało, przechodzi.

### 7.6 Agent i modele
- **Symfony AI Agent** z Toolboxem; zdarzenie `ToolCallRequested` wymusza politykę (operacje zapisu tylko jako
  propozycje, nigdy bezpośrednio).
- **Narzędzia agenta** (wewnętrzne + wystawione przez serwer MCP harnessu):
  `site.outline`, `page.tree`, `node.get`, `catalog.components`, `library.search`, `proposal.draft`,
  `proposal.revise`, `ui.*`, `material.analyze`, `data.preview`, `app.introspect` (Mate), `history.list`.
- **Analizatory materiału** zwracają **propozycję domeny** w jednym formacie (StructuredOutput):
  Excel/CSV (PhpSpreadsheet: typy, listy rozwijane → enumy, formuły → ExpressionLanguage, kolory → statusy),
  zdjęcia (EXIF + vision: daty, miejsca, tagi, duplikaty), PDF/dokumenty (struktura), archiwa eksportów
  (Instagram, Takeout — rozpoznanie po strukturze ZIP), URL (kanał, strona, playlista), temat do nauki (plan kursu),
  sam opis (wywiad przez `Questionnaire`). Nowy rodzaj materiału = nowy analizator, rejestrowany jak klocek.
- **Dostawcy** (konfiguracja jednym wpisem `.env`, z **Failover**):
  1. Hugging Face Inference Providers — domyślnie dla jury (kupon 20 $),
  2. bridge **ClaudeCode** / **Codex** — na abonamencie, bez kluczy API,
  3. lokalnie: **LM Studio** / **Ollama** / **Generic** (OpenAI-compatible).
- Pamięć rozmowy: MessageStore (Doctrine); kompresja kontekstu przy długich sesjach (InputProcessor).

### 7.7 MCP
- **Serwer MCP harnessu** — ten sam zestaw narzędzi co wewnętrzny agent: użytkownik może podpiąć własnego Claude
  Code / Codexa jako „mózg”; bramki i propozycje obowiązują go tak samo.
- **Serwer MCP introspekcji w aplikacji** — drzewo, katalog, dane, zdarzenia (resources z subskrypcjami
  `resources/updated` do podglądu na żywo).
- **Elicitation** (wersja 2026-07-28: wielorundowa, z podpisanym stanem `request_state`) — pytania i akceptacja
  propozycji, gdy klientem jest zewnętrzny agent.
- **MCP Apps** (`#[AsMcpApp]`) — szkic propozycji i wybrane widoki klocków jako interaktywne ekrany w Claude/ChatGPT
  (stretch, ale bardzo efektowny).
- **Nie** opieramy się na sampling/roots (usunięte w 2026-07-28).

### 7.8 Stos
PHP ≥ 8.4, Symfony 8.1, Symfony AI `^1.0@beta`, `mcp/sdk` `^0.9`, Doctrine ORM + Migrations, SQLite domyślnie
(MySQL/Postgres opcjonalnie), Messenger (transport Doctrine), Mercure (wbudowany hub w Docker Compose / Symfony
CLI), AssetMapper + importmap, Tailwind (dla kitu shadcn), UX: Turbo, Stimulus, Twig/Live Components, Toolkit
(shadcn), Icons, Map, Chart.js, Autocomplete, Dropzone, Image, Cropper, Pagination, Notify.

---

## 8. Mapa ekosystemu Symfony (do README)

| Rola | Część ekosystemu |
|---|---|
| Kręgosłup zdarzeń | Messenger (szyny, transporty, failure transport, middleware) |
| Cykl propozycji, procesy w klockach | Workflow |
| Reguły, formuły, wiązania danych | ExpressionLanguage |
| Cykliczne zadania | Scheduler |
| Powiadomienia | Notifier, Mailer, UX Notify |
| Uprawnienia (ludzie i klocki) | Security (votery) |
| Formularze, walidacja operacji | Form, Validator |
| Dane | Doctrine, Migrations, Uid |
| Pliki i media | Filesystem, Mime, UX Dropzone, Image, Cropper |
| Na żywo i animacje | Mercure, Turbo (Streams, View Transitions), Live Components |
| Interfejs | Twig Components, UX Toolkit (shadcn), Icons, Map, Chart.js, Autocomplete, Pagination |
| Bezpieczeństwo agenta | RateLimiter, Lock, HttpClient (scoped), Process (biała lista) |
| Generatory | MakerBundle |
| AI | Platform, Agent, Store, StructuredOutput, AI Bundle (profiler) |
| Introspekcja | Mate (Symfony + Monolog), Profiler, UX Inspector |
| Protokół | MCP Bundle (serwer, klient, MCP Apps), `mcp/sdk` |

---

## 9. Scenariusze demo (film 90 s i dla jurora)

**A. Excel → aplikacja** (30 s): wrzucenie arkusza zamówień → `Questionnaire` („Status jako workflow?”) → szkic
strony z tabelą, filtrami i pulpitem (stuby) → Zatwierdź → dane wjeżdżają na żywo → „zamówienia powyżej 10 tys.
zatwierdza szef” → szkic z nowym przyciskiem i stanem → gotowe.

**B. Zdjęcia → galeria** (30 s): „zrób mi galerię, oto zdjęcia” + 60 plików → agent przechodzi na nową stronę
`/galeria`, pokazuje szkic: albumy z wyjazdów, mapa → celownik na nagłówek: „to ma być bardziej jak magazyn
podróżniczy” → zmiana motywu i układu w szkicu → Zatwierdź → wyszukiwanie „pies na plaży” działa.
**Nowy klocek na żywo**: „pokaż pogodę z dnia zdjęcia” → agent nie znajduje klocka → kontrakt po ludzku → tryb cienia
na 60 historycznych zdarzeniach → plakietki pogody pojawiają się przy zdjęciach.

**C. Samonaprawa** (20 s): import z dziwnym formatem daty → komunikat w failure transport → propozycja naprawy z
wyjaśnieniem → Zatwierdź → ponowienie → wiersze wchodzą.

**Wariant B′ (Instagram)**: zamiast plików — ZIP z eksportu Instagrama → te same albumy z opisami i hashtagami.
Pokazuje, że źródło to tylko kolejny klocek.

**E. Explainer** (do README / dłuższej wersji filmu): „wytłumacz mi fotosyntezę na poziomie matury” → szkic kursu
(5 lekcji) → Zatwierdź → lekcja z diagramem i źródłami → quiz → odwiedzający pyta „AskThisPage”.

**D. Zewnętrzny agent** (10 s, opcjonalnie): ten sam szkic otwarty jako MCP App w Claude Desktop.

Dane demo w repo: przykładowy Excel (własny, bez danych osobowych) i zdjęcia na wolnej licencji.

---

## 10. Plan prac (28.10 – 22.11)

| Tydzień | Cel | Gotowe, gdy |
|---|---|---|
| **1** (28.10–3.11) | Szkielet: harness + aplikacja + Runtime Bundle; drzewo strony, renderer, operacje + walidacja, Proposal Workflow; panel czatu (Toolkit) z agentem; dostawcy z Failover | „Dodaj sekcję z nagłówkiem” → szkic → Zatwierdź → widać zmianę z animacją |
| **2** (4.11–10.11) | Klocki Records, SpreadsheetImport, Gallery, VisionTagger, MapView; analizatory Excel/zdjęć; Messenger + dziennik zdarzeń; celownik; polecenia UI | scenariusze A i B (bez nowego klocka) działają od zera |
| **3** (11.11–17.11) | Generator klocków + bramki + worktree + tryb cienia; samonaprawa z Mate; Approval, SemanticSearch, Dashboard; źródło Instagram; Explainer (LearningPath, Lesson, Quiz); serwer MCP harnessu | scenariusze B (nowy klocek, Instagram) i C działają, E w wersji podstawowej |
| **4** (18.11–22.11) | Szlif: README 5 min, Docker, testy, PHPStan, nagranie filmu, zgłoszenie; MCP Apps jeśli starczy czasu | juror na czystej maszynie dochodzi do demo w 5 minut |

Przez cały czas: błędy w `symfony/ai` / `php-sdk` / UX zgłaszamy od razu (issue z reprodukcją; PR, gdy poprawka
jest prosta) i zbieramy listę w README.

---

## 11. Ryzyka

| Ryzyko | Ograniczenie |
|---|---|
| Zakres (to najambitniejszy z pomysłów) | 8 klocków do końca zamiast 20 do połowy; drzewo + szkic + propozycje mają priorytet nad generatorem klocków |
| Słabszy model u jurora źle generuje kod | większość zmian to operacje na drzewie (mały, typowany kontrakt); generowanie kodu tylko dla nowych klocków, za bramkami |
| Bezpieczeństwo (czat zmieniający kod) | tryb lokalny, jeden właściciel; propozycje zamiast zapisu; biała lista komend; uprawnienia klocków; jasno w README |
| Beta Symfony AI / SDK się zmienia | przypięte wersje; nasze adaptery cienkie; błędy zgłaszane jako wartość dodana |
| Toolkit jest EXPERIMENTAL | komponenty są kopiowane do aplikacji — zmiany upstream nas nie psują |
| Animacje / szkic na słabszych przeglądarkach | View Transitions jako ulepszenie progresywne; bez nich zwykła podmiana |
| Debugowanie systemu zdarzeń | korelacja w kopercie, dziennik, widok `Trace`, Mate |

---

## 12. Decyzje

1. **Nazwa: Cadenza.** Kadencja — swobodna improwizacja solisty na solidnej konstrukcji utworu; tu: swoboda
   użytkownika na konstrukcji Symfony. Brak kolizji na Packagist i GitHub (sprawdzone 28.10.2026). Pitch:
   „Cadenza — your Symfony app improvises.”
2. **Biblioteka klocków — kompletna, w formacie paczek Composera.**
   - Monorepo: `harness/`, `runtime-bundle/`, `library/blocks/<Name>/`, `library/sources/<Name>/`,
     `library/analyzers/<Name>/`, `demo-app/` (aplikacja startowa), `docs/`.
   - Każdy klocek to **pełnoprawna paczka**: własny `composer.json`, `cadenza.json` (manifest, §6.2), kod, szablony,
     migracje i **własne testy** uruchamiane w CI osobno.
   - Instalacja w aplikacji = **kopia kodu** (jak `ux:install`) + wpis w `cadenza.lock` (nazwa, wersja, suma
     kontrolna plików). Aplikacja posiada swój kod, a harness wie, z której wersji biblioteki pochodzi i co
     użytkownik/agent zmienił lokalnie (`cadenza:block:diff`, `cadenza:block:update`).
   - Klocki tworzone przez agenta trafiają do `library/` jako `candidate` w tym samym formacie — biblioteka rośnie
     i jest gotowa do publikacji (split na osobne repozytoria po hackathonie).
3. **Jedna instalacja = jedna aplikacja użytkownika** (prostota, jasny model bezpieczeństwa). Wiele aplikacji —
   kierunek po hackathonie.
4. **Język**: interfejs dwujęzyczny EN/PL (Translator od pierwszego dnia); agent odpowiada w języku użytkownika.
5. **Zespół**: solo.
6. **Licencja**: MIT (jak Symfony AI i UX Toolkit).
7. **Kompletność**: projekt ma być kompletny, nie demo. Każdy element z §3–§7 ma działać end-to-end; zakres
   chronimy liczbą klocków (8 + źródło Instagram + Explainer), nie obcinaniem mechanizmów.

---

## Załącznik A. Stan ekosystemu (sprawdzony 28.10.2026)

- **Symfony AI v1.0.0-BETA1**: komponenty Platform (40+ dostawców, m.in. HuggingFace, LmStudio, Ollama, Generic,
  ClaudeCode, Codex, Failover, Cache), Agent (Toolbox, `ToolCallRequested`, pamięć, multi-agent, bridge'e Filesystem
  i Mcp), Store (25 baz, m.in. Sqlite, Doctrine), **Mate** (CLI introspekcji: kontener, profiler, logi; działa bez
  uruchamiania aplikacji), AI Bundle, MCP Bundle (serwer, klient, **MCP Apps**, subskrypcje, request state).
  Cookbook: human-in-the-loop, dynamic tools, generator–reviewer loop, multi-agent, context compression.
- **`mcp/sdk` v0.9.0**: serwer i klient, STDIO/HTTP, obie ery protokołu (handshake i bezstanowa 2026-07-28).
  W 2026-07-28 brak zapytań inicjowanych przez serwer (sampling/roots), elicitation wielorundowe. Brakuje jeszcze:
  OAuth klienta, rozszerzenia Tasks, pełnej zgodności ze specyfikacją.
- **Symfony UX v3.6.0**: Turbo, Stimulus, Twig/Live Components, Map, Chart.js, Autocomplete, Dropzone, Image, Cropper,
  Pagination, Icons, Notify, Inspector, Native, **Toolkit** (EXPERIMENTAL; kity shadcn ~90 komponentów — w tym
  `Message`, `MessageScroller`, `Trace`, `Questionnaire`, `Attachment`, `Sheet`, `Command`, `Skeleton` — oraz
  flowbite-4, bootstrap, common; przepisy z `manifest.json`, typy `component` i `block`, `ux:install` kopiuje pliki).

---

## 13. Stan realizacji (28.10.2026)

Zrobione i działające end-to-end (sprawdzone w przeglądarce; testy: `./cadenza test`, 34 testy):

| Obszar | Stan |
|---|---|
| Panel studia (§3.2), celownik (§3.3), szkic z różnicami po najechaniu, Przed/Po, zatwierdzanie z View Transitions (§3.4) | ✅ |
| Oś czasu, podgląd wersji, przywracanie, „Cofnij”, „Zacznij od nowa” (§3.6) | ✅ |
| Drzewo strony i język operacji, walidacja katalogiem, deterministyczne id, rebase szkiców (§4–5) | ✅ |
| Proposal Workflow (Symfony Workflow), dwa serwery MCP aplikacji (agent/control), harness jako klient MCP (§7.2, §7.7) | ✅ |
| Kierunki stylu (6), wzorce sekcji (15), hero w 4 wariantach, warianty strony obok siebie z żywymi miniaturami | ✅ |
| Biblioteka mediów, zdjęcia z Wikimedia (Pexels/Unsplash z kluczem), wybór zdjęcia w panelu, generowanie FLUX (z kluczem HF) | ✅ |
| Szkic rysowany ołówkiem / wklejony obraz → układ strony (model wizyjny) | ✅ |
| Zdjęcia z telefonu przez kod QR, EXIF, albumy wyjazdów (Nominatim), żywa galeria | ✅ |
| Konektor Instagram jako pierwsza paczka biblioteki (`library/sources/instagram`): eksport ZIP + Instagram Login, import w tle (Messenger), codzienna synchronizacja (Scheduler) | ✅ (OAuth bez testu na koncie) |
| Kolekcje z arkuszy (§6.3 Records + SpreadsheetImport): analiza typów kolumn, import, siatka z filtrami, tabela, strona rekordu | ✅ |
| Strona ucząca: interaktywny quiz | ✅ (LearningPath/Flashcards/AskThisPage — nie) |
| Diagnostyka przez Symfony AI Mate, izolacja błędów renderu, model zapasowy, tolerancja błędów modeli (JsonRepair, lenient resolver), polityka narzędzi w kodzie | ✅ |
| Uruchomienie jednym poleceniem, README EN, licencja MIT, lista zgłoszeń do upstreamu, scenariusz filmu | ✅ |

Nie zrobione (świadomie poza zakresem lub na później):
- tworzenie nowych klocków z kodem przez agenta, bramki PHPStan/testy, worktree i tryb cienia (§6.4, §7.3–7.4);
- samonaprawa z ponawianiem komunikatów z failure transport (§7.5) — jest diagnostyka przez Mate, brak pętli naprawy;
- wizualna kontrola szkiców zrzutami Panther + model wizyjny;
- MCP Apps (§7.7), interfejs EN/PL przez Translator (panel ma własny słownik EN/PL);
- PHPStan na poziomie max (testy są, analiza statyczna nie).

Decyzje podjęte w trakcie:
- Modele: lokalne GPU zajęte → darmowe modele w chmurze (Ollama Cloud `beam`, OpenRouter `:free`); klucze z 1Password
  tylko w środowisku procesu (`harness/bin/serve-with-1password`).
- Instagram: bez scrapingu i bez logowania hasłem; dla kont prywatnych ścieżką domyślną jest telefon (QR), potem eksport.
- Warianty: treść pisana raz, warianty różnią się kierunkiem stylu (mniej tokenów, mniej błędów modeli).
