Implementační dokumentace k 1. úloze do IPP 2021/2022
Jméno a příjmení: Vojtěch Dvořák
Login: xdvora3o

# Intepret

## Úvod
Podobně jako u syntaktického analyzátoru byl i v případě intepretu kladen důraz zejména na jeho rozšiřitelnost a přehledenost. Hlavním aparáty, kterými toho bylo dosaženo, bylo OOP a zvolená koncepce návrhu. Před implementací jednotlivých instrukcí byl vytvořen framework, který umožnil zpracovávat a vykonávat, instrukce ve vstupním XML souboru jednotným způsobem.

Díky tomu byl sice návrh o něco složitější, protože bylo nutné navrhnout vhodné abstrakce pro instrukce, jejich operandy apod., nicméně samotná implenetace intstrukcí a potažmo i jejich přidávání bylo velmi snadné až triviální.
Pro lepší přehlednost je implementace rozdělena do několika souborů:

+ `interpret.py` obsahuje hlavní tělo intepretu a definicí třídy ConfigCreator, která svými statickými metodami zajišťuje vytváření konfiguračního slovníku

+ `errors.py` obsahuje návratové kódy, definice vyjímek a pomocné funkce pro jejich zpracování
iparser.py soubor se třídami IParser a SAnalyzer, jejichž instance jsou zodpovědné za zpracování vstupního XML souboru a statické sémantické kontroly

+ `program.py` soubor s abstrakcemi instrukcí (Instruction), operandů (Operand), prováděného programu (Program) a s jinými důležitými třídami, které jsou detailně popsány níže

+ `lang.py` obsahuje třídy s popisem jazyka (Lang), implentací intrukcí (Op) a pomocnými funkcemi pro lepší (generickou) implementaci (Utils)

Jednotlivé fáze zpracování vstupního XML souboru a detailní popis zodpovědností tříd je uveden níže.

## Analýza vstupu
Při spuštění intepretu jsou nejdříve pomocí třídy ConfigCreator zpracovány parametry skriptu. Tato třída provede kontrolu jejich platnosti a vytvoří konfigurační slovník v němž jsou specifikovány vstupní soubory a případně také skupiny statistik. Dále je vstup analyzován pomocí instance třídy IParser. Ta  nejdříve zkontroluje spravnost vstupního XML souboru a převede ho do interní reprezentace pomocí funkcí z modulů xml.dom.minidom a xml.parsers.expat. Následně nad takto zpracovaným programem provede sémantické kontroly aniž by ho prováděla. K tomuto účelu je využita třída SAnalyzer. Tato analýza odhalí např. redefinici proměnných v globálním rámci, skok na neexistující návěští nebo redefinici navěští. V případě, že je během nějaké fáze tétot vstupní analýzy detekována chyba, je vyhozena výjimka, jež je obsloužena hlavním tělem skriptu. Obsluha výjimky zahrnuje vypsání chybového hlášení a také ukončení intepretu s přílušným návratovým kódem.

## Interní reprezentace programu
Jak již bylo zmíněno výše, vstupní reprezentace kódu v XML je před jeho prováděním převedena do interní reprezentace. Ta je tvořena objektem typu Program, který obsahuje pole instrukcí  seřazené dle pořadí, v jakém jsou provedeny, dále kontext programu (ProgramContext) a sběrač statistik (StatCollector).

Jednotlivé instrukce jsou uchovávány jako objekty typu Instruction, které kromě svého operačního kódu, pořadového čísla a pole s operandy instrukce obsahuje jako atribut také odkaz na metodu, která danou intrukci implementuje.

Interní reprezentací operandů jsou opět objekty (typ Operand), které v tomto případě obsahují typ a hodnotu.
Jak operandy, tak i instrukce jsou navíc podle svých typů rozlišeny podtřídami uvedených tříd. Například pro instrukci definující návěští existuje třída Label (potomek Instruction), který ve své implentaci reflektuje pasivitu této instrukce.

Objekt typu ProgramContext obsahuje atributy v nichž je uchováván stav prováděného programu. Tím je myšlen zejména obsah rámců s proměnnými, zasobník rámců, datový zásobník, zásobník volání a index prováděné instrukce v poli instrukcí (v objektu typu Program). Zjednodušeně řečeno se jedná o paměť virtuálního počítače provádějícího kód.

Pro implementaci zasobníků bylo použito pole, které je obaleno třídou Stack pro snazší impelentaci metod pracujicích se zásobníky.
Podobně je tomu i u implentace rámců s proměnnými. Narozdíl od zásobníků, kde je stěžejní datovou strukturou pole, zde tuto úlohu plní slovník pro svoji schopnost asociovat jméno proměnné s daty.

## Provádění programu
Po analýze vstupního souboru a vytvoření vnitřní je programu je možné ho interpretovat. Intepretace je zahájena nastavením indexu prováděné instrukce na počáteční hodnotu, čímž je program z nečinného stavu, kdy byl tento index nastaven na hodnotu None, přepnut do aktivního stavu.

Výše uvedené abstrakce umožňují poté vykonávat jednotlivé instrukce v cyklu generickým způsobem. 
Při provádění instrukce je zavolána příslušná funkce implentující instrukci (odkaz na ní je v objektu představujícím intrukci). Tyto funkce mění programový kontext (např. modifikují datový zásobník, přidají proměnnou do rámce...). Po jejím úspěšném provedení jsou volitelně aktualizovány statistiky a vždy je inkrementovám index prováděné instrukce, čímž se program přesune k následující instrukci. Jinou modifikací indexu je pak docíleno skoků v rámci prováděného programu.

Jakmile je index instrukce za hranicemi pole s instrukcemi (nebo při zavolání intrukce EXIT) je opět nastaven na hodnotu None, což způsobí ukončení prováděného programu.

V případě že dojde k běhové chybě je tato skutečnost pomocí příslušné výjimky propagována do hlavního těla, kde je obsloužena.

## Použité návrhové vzory
Pro implemetaci instrukcí byl využit návrhový vzor Command. Instrukce jsou převedeny do interní reprezentace, je jim přiřazena implementace a poté jsou hromadně prováděny. Do budoucna může být také benefitující snadná implentace krokování nebo jiného interaktivního ladění programu, což je způsobeno právě použitím tohoto návrhového vzoru.

# Testovací rámec

## Úvod
Funkcionalita testovacího rámce je opět rozdělena mezi objekty. Struktura testovacího rámce je však i vzhledem k jeho rozsahu o poznání jednodušší.

## Příprava
Nejdříve jsou zpracovány argumenty příkazové řádky, kterými je možné modifikovat implicitní nastavení. Informace o nastavení jsou uchovány jako statické atributy ve třídě Options. V rámci zpracování argumentů jsou také odchyceny jejich nedovolené kombinace a přítomnost testovaných skriptů v uvedených adresářích. Poté je spuštěno samotné testování.

## Testování a verifikace
Za testování a porovnávání získaných výsledků je zodpovědný objekt typu Tests. Jeho metoda run prohledá aktuální adresář (zprvu zadaný položkou $directory ve třídě Options) a najde v něm všechny zdrojové soubory s příponou src. Pro každý z těchto souborů zkontroluje přítomnost odpovídajících souborů se vstupem, očekávaným výsledkem a očekávaným návratovým kódem. V případě, že některý z nich nenalezne, vygeneruje chybějící soubory dle specifikace.

Po této přípravě jsou zavolány testované skripty metodou test, jež na základě nastavení skriptu vybere příslušný příkaz pro jejich spuštění a pomocí funkce exec je zavolá. Získané výstupy jsou uchovány v dočasném souboru s příponou tmp nebo xml.tmp (v závislosti na tom, který skript je testován).
Následuje porovnání výsledku s očekávaným výstupem, které je provedeno metodou compare. Tato metoda nejprve zkontroluje návratový kód provedeného skriptu a poté vybere vhodný nástroj pro porovnání (diff nebo jexamxml). Ten spustí opět pomocí funkce exec, přičemž rozdíly mezi očekávaným a reálným výstupem jsou uloženy do dočasného souboru s příponou diff.

Následuje vypsání výsledku do výstupní zprávy, úklid a případně také rekurzivní volání metody run pro podadresáře.

## Výstupní zpráva
Během testování je průběžně vytvářena také výstupní zpráva výpisem na standardní výstup, který zajišťuje třída HTML. Výstupní dokument je doplněn také o kód v JavaScriptu zajišťující funkci interaktivních prvků (skrytí úspěšným testů) a specifikaci vizuálního stylu v CSS, který dokument zpřehledňuje. Výsledky jednotlivých testů jsou uspořádány do seznamu, který je navíc organizován do obálek podle podadresářů (v případě, že je aktivována možnost --recusive). Po vypsání všech výsledků testů je nakonec vypsán souhrn a ten je pomocí CSS atributu order vložen na začátek seznamu.
Souběžně s výstupní zprávou v HTML jsou výsledky vypisovány také na standardní chybový výstup ve zjednodušené formě, aby bylo možné snadno kontrolovat průběh testování.


