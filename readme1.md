Implementační dokumentace k 1. úloze do IPP 2021/2022
Jméno a příjmení: Vojtěch Dvořák
Login: xdvora3o

## Úvod
Při řešení projektu byl kromě aspektů a funkcionality dané zadáním kladen důraz také na rozšiřitelnost vytvářeného analyzátoru. Tento přístup byl zvolen pro případ, že by došlo např. k nějakým upravám zdrojového jazyka nebo pokud by byly v důsledku špatného studia zadání některé elementy zdrojového jazyka opomenuty. Do výsledného řešení by tedy nemělo být nijak komplikované přidat např. podporu dalších datových typů apod. I z tohoto důvodu bylo využito OOP, díky kterému je navíc řešení přehledně rozděleno na logické části.

Skript jako takový se zkládá ze tří hlavních částí - hlavního těla, analyzátoru kódu (třída `Parser`) a třídy komunikující s uživatelem (reprezentována třídou `UI`). Kromě těchto hlavních částí zde najdeme také přídavnou třídu s názvem `StatCollector`, jež se stará o sbírání statistik. Řízení je po spuštění skriptu předáno nejdříve do hlavního těla, kde je vytvořena instance třídy `UI`, pomocí které jsou zpracovány argumenty z příkazové řádky. Na základě tohoto zpracování je pak rozhodnuto o dalším pokračování programu nebo a vypsání nápovědy (popř. chybové hlášky) a jeho ukončení.

## Analyzátor zdrojového kódu
Při návrhu analyzátoru kódu byly využity znalosti z předmětu IFJ. Syntaktický analyzátor (instance třídy `Parser`) pomocí metod třídy reprzentující lexikální analyzátor (třída `Scanner`) získává tokeny. Jejich typ poté porovnává s očekávanými typy a na základě výsledku provnání buďto skončí program s chybou nebo pokračuje v analýze dál. Průbežně přitom připravuje cílovou reprezentaci kódu v XML prostřednictvím k tomu určené třídy (`Printer`), v jejíž implementaci můžeme nalézt funkce z knihovny XMLWriter. Pokud je nalezena ve zdrojovém kódu chyba, je výsledná reprezentace zahozena.

Implementace lexikálního analyzátoru využívá jak konečného automatu, tak i regulárních výrazů. Pomocí konečného automatu jsou nejdříve rozlišeny platné tokeny od bílých znaků, komentářů apod. Platné tokeny jsou poté klasifikovány pomocí regulárních výrazů a funkce `preg_match`. Implementace konečného automatu a funkce klasifikující tokeny pomoc regulárních výrazů jsou k nalezení ve třídě jménen `Table` (soubor tables.php), v níž najdeme popisy také ostatních elementů zdrojového jazyka. Díky tomu není nutné i při poměrně radikálních změnách zdrojového jazyka upravovat více tříd v různých souborech.

Výsledkem klasifikace tokenů nemusí být pouze konkrétní typ tokenu ale celá množina (pole) typů, kterým načtený token odpovídá. Výsledný typ je pak určen až na základě očekávaného typu tokenu (na základě kontextu). Díky tomu je možné využívat např. název instrukce jako návěští.

Syntaktická analýza je prováděna ad hoc metodou. Nejdříve je zkontrolována hlavička zdrojového kódu a poté se v cyklu opakuje kontrola instrukce a jejích operandů dokud není nalezen konec vstupu. Počet a typy operandů instrukce jsou zjišťovány z výše zmíněné statické třídy `Table`.

V případě, že analyzátor kódu narazí na syntaktickou nebo lexikální chybu, je ukončen s příslušnou návratovou hodnotou a na standardní chybový výstup je vypsána chybová hláška s popisem chyby a souřadnicemi jejího výskytu.

## Použité návrhové vzory
Ve třídách `Scanner` (lexikální analyzátor) a `StatCollector` (sběrač statistik) byl využit návrhový vzor jedináček (singleton) za účelem zachování konzistence dat. Jelikož je nutné, aby byl vstup čten právě jedním lexikálním analyzátorem, museli bychom jeho instanci předávat všem ostatním objektům, jež potřebují jeho služby (konkrétně se jedná o syntaktický analyzátor a objekt třídy `UI` vypisující chybová hlášení). Vlastnosti jednináčka zajišťují, že je možné přistoupit pomocí volání funkce statické metody `instantiate` právě k jedné originální instanci lex. analyzátoru. Aby se zamezilo vytváření dalších instancí skrze klíčové slovo `new` je viditelnost konstruktoru v této třídě omezena pomocí klíčového slova `private`. Podobně je tomu u třídy `StatCollector`, jejíž instance zajišťuje sběr statistik z kódu.

## Sbírání statistik
Analyzátor umožňuje také sbírat statistiky o zdrojovém kódu a ukládat je do souborů (viz rozšíření STATP). Při volbě přepínače `--jumps` jsou do statistik podle zadání započítávány i instrukce pro volání funkcí a návraty z nich. U přepínačů `--fwjumps`, `--backjumps` a `--badjumps` jsou započítávány pouze instrukce pro podmíněné a nepodmíněné skoky. Cíl skoku při provádění instrukce `RETURN` totiž zavisí na stavu zásobníku volání, který nelze za všech podmínek snadno staticky určit. Instrukce `CALL`je pak z těchto statistik vynechána z důvodů symetrie.