<?php

/**
 * Reading one ABI argument value out of what a person typed.
 *
 * @package olegabr/eth-abi
 */

declare(strict_types=1);

namespace Olegabr\EthAbi;

/**
 * Turns the text of one input field into the value the encoder expects, and
 * explains refusal with a code instead of a sentence.
 *
 * The rules follow the Solidity ABI specification, because the chain is the one
 * authority on what a value may be:
 *
 *  * `uint<M>` / `int<M>` - `M` bits, a multiple of 8, at most 256; `uint` and
 *    `int` mean 256. Written as decimal, no sign for `uint`;
 *  * `address` - 20 bytes; pasted without `0x` it is accepted and lowercased,
 *    because two spellings of one address must not be two different values;
 *  * `bool` - `true`/`false`, and `1`/`0` because that is what people type;
 *  * `bytes<M>` - exactly `M` bytes of hex; `bytes` - any even number of hex
 *    digits; `string` - as typed;
 *  * `T[]` and `T[k]` - a JSON list, or a comma-separated list, which is what a
 *    single input field can hold.
 *
 * `tuple` is refused with a code instead of a guess: its component names are
 * part of the contract's interface, and a text field is the wrong place to
 * invent them.
 *
 * **No sentences here.** A refusal is a {@see Problem} - a code plus the values
 * that go into a message. Translation belongs to the caller (`sprintf` and the
 * WordPress translation functions live there), and this class has no idea what
 * language a site speaks.
 *
 * **No arithmetic here beyond the bounds check**, and the bounds are constants
 * of the specification, not computations: `2^k` is looked up, never built. A
 * measured reason (PHP 8.5, x86_64): building `2^256` by doubling a decimal
 * string on every validation cost ~470 µs per form field; the lookup costs
 * ~60 ns. Anything wider than a comparison is {@see https://github.com/brick/math}.
 *
 * @since 0.1.0
 */
final class Type
{
    /**
     * `2 ** k` as decimal digits, for every bound the ABI can ask for.
     *
     * `uint<M>` needs `2^M` (M = 8, 16, … 256) and `int<M>` needs `2^(M-1)`
     * (M-1 = 7, 15, … 255), so two thirds of the exponents below are unused -
     * they are kept because a missing key would be a runtime surprise, and the
     * whole table is one page of the specification written out.
     *
     * @var array<int,string>
     */
    private const LIMITS = array(
        0 => '1',
        1 => '2',
        2 => '4',
        3 => '8',
        4 => '16',
        5 => '32',
        6 => '64',
        7 => '128',
        8 => '256',
        9 => '512',
        10 => '1024',
        11 => '2048',
        12 => '4096',
        13 => '8192',
        14 => '16384',
        15 => '32768',
        16 => '65536',
        17 => '131072',
        18 => '262144',
        19 => '524288',
        20 => '1048576',
        21 => '2097152',
        22 => '4194304',
        23 => '8388608',
        24 => '16777216',
        25 => '33554432',
        26 => '67108864',
        27 => '134217728',
        28 => '268435456',
        29 => '536870912',
        30 => '1073741824',
        31 => '2147483648',
        32 => '4294967296',
        33 => '8589934592',
        34 => '17179869184',
        35 => '34359738368',
        36 => '68719476736',
        37 => '137438953472',
        38 => '274877906944',
        39 => '549755813888',
        40 => '1099511627776',
        41 => '2199023255552',
        42 => '4398046511104',
        43 => '8796093022208',
        44 => '17592186044416',
        45 => '35184372088832',
        46 => '70368744177664',
        47 => '140737488355328',
        48 => '281474976710656',
        49 => '562949953421312',
        50 => '1125899906842624',
        51 => '2251799813685248',
        52 => '4503599627370496',
        53 => '9007199254740992',
        54 => '18014398509481984',
        55 => '36028797018963968',
        56 => '72057594037927936',
        57 => '144115188075855872',
        58 => '288230376151711744',
        59 => '576460752303423488',
        60 => '1152921504606846976',
        61 => '2305843009213693952',
        62 => '4611686018427387904',
        63 => '9223372036854775808',
        64 => '18446744073709551616',
        65 => '36893488147419103232',
        66 => '73786976294838206464',
        67 => '147573952589676412928',
        68 => '295147905179352825856',
        69 => '590295810358705651712',
        70 => '1180591620717411303424',
        71 => '2361183241434822606848',
        72 => '4722366482869645213696',
        73 => '9444732965739290427392',
        74 => '18889465931478580854784',
        75 => '37778931862957161709568',
        76 => '75557863725914323419136',
        77 => '151115727451828646838272',
        78 => '302231454903657293676544',
        79 => '604462909807314587353088',
        80 => '1208925819614629174706176',
        81 => '2417851639229258349412352',
        82 => '4835703278458516698824704',
        83 => '9671406556917033397649408',
        84 => '19342813113834066795298816',
        85 => '38685626227668133590597632',
        86 => '77371252455336267181195264',
        87 => '154742504910672534362390528',
        88 => '309485009821345068724781056',
        89 => '618970019642690137449562112',
        90 => '1237940039285380274899124224',
        91 => '2475880078570760549798248448',
        92 => '4951760157141521099596496896',
        93 => '9903520314283042199192993792',
        94 => '19807040628566084398385987584',
        95 => '39614081257132168796771975168',
        96 => '79228162514264337593543950336',
        97 => '158456325028528675187087900672',
        98 => '316912650057057350374175801344',
        99 => '633825300114114700748351602688',
        100 => '1267650600228229401496703205376',
        101 => '2535301200456458802993406410752',
        102 => '5070602400912917605986812821504',
        103 => '10141204801825835211973625643008',
        104 => '20282409603651670423947251286016',
        105 => '40564819207303340847894502572032',
        106 => '81129638414606681695789005144064',
        107 => '162259276829213363391578010288128',
        108 => '324518553658426726783156020576256',
        109 => '649037107316853453566312041152512',
        110 => '1298074214633706907132624082305024',
        111 => '2596148429267413814265248164610048',
        112 => '5192296858534827628530496329220096',
        113 => '10384593717069655257060992658440192',
        114 => '20769187434139310514121985316880384',
        115 => '41538374868278621028243970633760768',
        116 => '83076749736557242056487941267521536',
        117 => '166153499473114484112975882535043072',
        118 => '332306998946228968225951765070086144',
        119 => '664613997892457936451903530140172288',
        120 => '1329227995784915872903807060280344576',
        121 => '2658455991569831745807614120560689152',
        122 => '5316911983139663491615228241121378304',
        123 => '10633823966279326983230456482242756608',
        124 => '21267647932558653966460912964485513216',
        125 => '42535295865117307932921825928971026432',
        126 => '85070591730234615865843651857942052864',
        127 => '170141183460469231731687303715884105728',
        128 => '340282366920938463463374607431768211456',
        129 => '680564733841876926926749214863536422912',
        130 => '1361129467683753853853498429727072845824',
        131 => '2722258935367507707706996859454145691648',
        132 => '5444517870735015415413993718908291383296',
        133 => '10889035741470030830827987437816582766592',
        134 => '21778071482940061661655974875633165533184',
        135 => '43556142965880123323311949751266331066368',
        136 => '87112285931760246646623899502532662132736',
        137 => '174224571863520493293247799005065324265472',
        138 => '348449143727040986586495598010130648530944',
        139 => '696898287454081973172991196020261297061888',
        140 => '1393796574908163946345982392040522594123776',
        141 => '2787593149816327892691964784081045188247552',
        142 => '5575186299632655785383929568162090376495104',
        143 => '11150372599265311570767859136324180752990208',
        144 => '22300745198530623141535718272648361505980416',
        145 => '44601490397061246283071436545296723011960832',
        146 => '89202980794122492566142873090593446023921664',
        147 => '178405961588244985132285746181186892047843328',
        148 => '356811923176489970264571492362373784095686656',
        149 => '713623846352979940529142984724747568191373312',
        150 => '1427247692705959881058285969449495136382746624',
        151 => '2854495385411919762116571938898990272765493248',
        152 => '5708990770823839524233143877797980545530986496',
        153 => '11417981541647679048466287755595961091061972992',
        154 => '22835963083295358096932575511191922182123945984',
        155 => '45671926166590716193865151022383844364247891968',
        156 => '91343852333181432387730302044767688728495783936',
        157 => '182687704666362864775460604089535377456991567872',
        158 => '365375409332725729550921208179070754913983135744',
        159 => '730750818665451459101842416358141509827966271488',
        160 => '1461501637330902918203684832716283019655932542976',
        161 => '2923003274661805836407369665432566039311865085952',
        162 => '5846006549323611672814739330865132078623730171904',
        163 => '11692013098647223345629478661730264157247460343808',
        164 => '23384026197294446691258957323460528314494920687616',
        165 => '46768052394588893382517914646921056628989841375232',
        166 => '93536104789177786765035829293842113257979682750464',
        167 => '187072209578355573530071658587684226515959365500928',
        168 => '374144419156711147060143317175368453031918731001856',
        169 => '748288838313422294120286634350736906063837462003712',
        170 => '1496577676626844588240573268701473812127674924007424',
        171 => '2993155353253689176481146537402947624255349848014848',
        172 => '5986310706507378352962293074805895248510699696029696',
        173 => '11972621413014756705924586149611790497021399392059392',
        174 => '23945242826029513411849172299223580994042798784118784',
        175 => '47890485652059026823698344598447161988085597568237568',
        176 => '95780971304118053647396689196894323976171195136475136',
        177 => '191561942608236107294793378393788647952342390272950272',
        178 => '383123885216472214589586756787577295904684780545900544',
        179 => '766247770432944429179173513575154591809369561091801088',
        180 => '1532495540865888858358347027150309183618739122183602176',
        181 => '3064991081731777716716694054300618367237478244367204352',
        182 => '6129982163463555433433388108601236734474956488734408704',
        183 => '12259964326927110866866776217202473468949912977468817408',
        184 => '24519928653854221733733552434404946937899825954937634816',
        185 => '49039857307708443467467104868809893875799651909875269632',
        186 => '98079714615416886934934209737619787751599303819750539264',
        187 => '196159429230833773869868419475239575503198607639501078528',
        188 => '392318858461667547739736838950479151006397215279002157056',
        189 => '784637716923335095479473677900958302012794430558004314112',
        190 => '1569275433846670190958947355801916604025588861116008628224',
        191 => '3138550867693340381917894711603833208051177722232017256448',
        192 => '6277101735386680763835789423207666416102355444464034512896',
        193 => '12554203470773361527671578846415332832204710888928069025792',
        194 => '25108406941546723055343157692830665664409421777856138051584',
        195 => '50216813883093446110686315385661331328818843555712276103168',
        196 => '100433627766186892221372630771322662657637687111424552206336',
        197 => '200867255532373784442745261542645325315275374222849104412672',
        198 => '401734511064747568885490523085290650630550748445698208825344',
        199 => '803469022129495137770981046170581301261101496891396417650688',
        200 => '1606938044258990275541962092341162602522202993782792835301376',
        201 => '3213876088517980551083924184682325205044405987565585670602752',
        202 => '6427752177035961102167848369364650410088811975131171341205504',
        203 => '12855504354071922204335696738729300820177623950262342682411008',
        204 => '25711008708143844408671393477458601640355247900524685364822016',
        205 => '51422017416287688817342786954917203280710495801049370729644032',
        206 => '102844034832575377634685573909834406561420991602098741459288064',
        207 => '205688069665150755269371147819668813122841983204197482918576128',
        208 => '411376139330301510538742295639337626245683966408394965837152256',
        209 => '822752278660603021077484591278675252491367932816789931674304512',
        210 => '1645504557321206042154969182557350504982735865633579863348609024',
        211 => '3291009114642412084309938365114701009965471731267159726697218048',
        212 => '6582018229284824168619876730229402019930943462534319453394436096',
        213 => '13164036458569648337239753460458804039861886925068638906788872192',
        214 => '26328072917139296674479506920917608079723773850137277813577744384',
        215 => '52656145834278593348959013841835216159447547700274555627155488768',
        216 => '105312291668557186697918027683670432318895095400549111254310977536',
        217 => '210624583337114373395836055367340864637790190801098222508621955072',
        218 => '421249166674228746791672110734681729275580381602196445017243910144',
        219 => '842498333348457493583344221469363458551160763204392890034487820288',
        220 => '1684996666696914987166688442938726917102321526408785780068975640576',
        221 => '3369993333393829974333376885877453834204643052817571560137951281152',
        222 => '6739986666787659948666753771754907668409286105635143120275902562304',
        223 => '13479973333575319897333507543509815336818572211270286240551805124608',
        224 => '26959946667150639794667015087019630673637144422540572481103610249216',
        225 => '53919893334301279589334030174039261347274288845081144962207220498432',
        226 => '107839786668602559178668060348078522694548577690162289924414440996864',
        227 => '215679573337205118357336120696157045389097155380324579848828881993728',
        228 => '431359146674410236714672241392314090778194310760649159697657763987456',
        229 => '862718293348820473429344482784628181556388621521298319395315527974912',
        230 => '1725436586697640946858688965569256363112777243042596638790631055949824',
        231 => '3450873173395281893717377931138512726225554486085193277581262111899648',
        232 => '6901746346790563787434755862277025452451108972170386555162524223799296',
        233 => '13803492693581127574869511724554050904902217944340773110325048447598592',
        234 => '27606985387162255149739023449108101809804435888681546220650096895197184',
        235 => '55213970774324510299478046898216203619608871777363092441300193790394368',
        236 => '110427941548649020598956093796432407239217743554726184882600387580788736',
        237 => '220855883097298041197912187592864814478435487109452369765200775161577472',
        238 => '441711766194596082395824375185729628956870974218904739530401550323154944',
        239 => '883423532389192164791648750371459257913741948437809479060803100646309888',
        240 => '1766847064778384329583297500742918515827483896875618958121606201292619776',
        241 => '3533694129556768659166595001485837031654967793751237916243212402585239552',
        242 => '7067388259113537318333190002971674063309935587502475832486424805170479104',
        243 => '14134776518227074636666380005943348126619871175004951664972849610340958208',
        244 => '28269553036454149273332760011886696253239742350009903329945699220681916416',
        245 => '56539106072908298546665520023773392506479484700019806659891398441363832832',
        246 => '113078212145816597093331040047546785012958969400039613319782796882727665664',
        247 => '226156424291633194186662080095093570025917938800079226639565593765455331328',
        248 => '452312848583266388373324160190187140051835877600158453279131187530910662656',
        249 => '904625697166532776746648320380374280103671755200316906558262375061821325312',
        250 => '1809251394333065553493296640760748560207343510400633813116524750123642650624',
        251 => '3618502788666131106986593281521497120414687020801267626233049500247285301248',
        252 => '7237005577332262213973186563042994240829374041602535252466099000494570602496',
        253 => '14474011154664524427946373126085988481658748083205070504932198000989141204992',
        254 => '28948022309329048855892746252171976963317496166410141009864396001978282409984',
        255 => '57896044618658097711785492504343953926634992332820282019728792003956564819968',
        256 => '115792089237316195423570985008687907853269984665640564039457584007913129639936',
    );

    /**
     * The canonical spelling of a type.
     *
     * `uint` and `int` are aliases for the 256-bit forms, and so is `byte` for
     * `bytes1`; everything else is already canonical. Case matters: `Address` is
     * not a Solidity type.
     *
     * @since 0.1.0
     *
     * @param string $type Type as it appears in the ABI.
     *
     * @return string Canonical type, or the input when it is not recognised.
     */
    public static function canonical(string $type): string
    {
        $type = trim($type);

        return match ($type) {
            'uint' => 'uint256',
            'int' => 'int256',
            'byte' => 'bytes1',
            default => $type,
        };
    }

    /**
     * Whether a value of this type can be read from one text field at all.
     *
     * @since 0.1.0
     *
     * @param string $type Type as it appears in the ABI.
     *
     * @return bool False for tuples, nested arrays and malformed types.
     */
    public static function isSupported(string $type): bool
    {
        $type = self::canonical($type);

        if (str_starts_with($type, 'tuple')) {
            return false;
        }

        $element = self::elementOf($type);

        if (null === $element) {
            return 1 === preg_match('/^(u?int\d*|address|bool|string|bytes\d*)$/', $type);
        }

        // A nested array is a JSON shape, not a line of text: one field cannot
        // tell `[[1,2],[3,4]]` from a typo with any confidence.
        if (str_starts_with($element[0], '[') || null !== self::elementOf($element[0])) {
            return false;
        }

        return self::isSupported($element[0]);
    }

    /**
     * The reason a value does not fit, or null when it does.
     *
     * A form wants the code next to the field, not an exception it has to catch
     * and translate; a caller that cannot continue calls {@see self::parse()}.
     *
     * @since 0.1.0
     *
     * @param string $type  Type as it appears in the ABI.
     * @param mixed  $input What the field holds.
     *
     * @return Problem|null
     */
    public static function problem(string $type, mixed $input): ?Problem
    {
        $type = self::canonical($type);

        if (is_bool($input) || is_int($input)) {
            $input = (string) $input;
        }

        if (is_array($input)) {
            return self::problemArray($type, $input);
        }

        if (!is_scalar($input)) {
            return new Problem('value.not_scalar');
        }

        $text = trim((string) $input);
        $element = self::elementOf($type);

        if (null !== $element) {
            // `T[k]`: the count is part of the interface, so the list has to have
            // exactly that many entries - a node would reject the call anyway.
            [$inner, $count] = $element;

            $list = self::listOf($text);

            if (null === $list) {
                // A list that starts like JSON and is not JSON is a typo, not an
                // empty list: `[1,2` must not become "no arguments".
                return new Problem('array.not_json', [$type]);
            }

            $problem = self::problemArray($inner, $list);

            if (null !== $problem) {
                return $problem;
            }

            if ($count > 0 && count($list) !== $count) {
                return new Problem('array.count', [$count, $type, count($list)]);
            }

            return null;
        }

        if ('address' === $type) {
            return self::problemAddress($text);
        }

        if ('bool' === $type) {
            return in_array(strtolower($text), ['true', 'false', '1', '0'], true)
                ? null
                : new Problem('bool.value');
        }

        if ('string' === $type) {
            return null;
        }

        if ('bytes' === $type) {
            return self::problemBytes($text, 0);
        }

        if (1 === preg_match('/^bytes(\d+)$/', $type, $matches)) {
            return self::problemBytes($text, (int) $matches[1]);
        }

        if (1 === preg_match('/^u?int(\d+)?$/', $type, $matches)) {
            return self::problemInteger($type, $text, $matches);
        }

        return new Problem('type.unknown', [$type]);
    }

    /**
     * Reads a value, or throws with the code in the exception.
     *
     * The exception message is the code, not a sentence: a caller that shows the
     * message to a person has to translate it anyway, and a caller that logs it
     * wants the code.
     *
     * @since 0.1.0
     *
     * @param string $type  Type as it appears in the ABI.
     * @param mixed  $input What the field holds.
     *
     * @throws InvalidValue When the value does not fit the type.
     *
     * @return string The value, normalised to one spelling.
     */
    public static function parse(string $type, mixed $input): string
    {
        $problem = self::problem($type, $input);

        if (null !== $problem) {
            throw new InvalidValue($problem);
        }

        return self::normalise($type, $input);
    }

    /**
     * The one spelling this class accepts for a value that passed
     * {@see self::problem()}.
     *
     * @since 0.1.0
     *
     * @param string $type  Type as it appears in the ABI.
     * @param mixed  $input Value.
     *
     * @return string
     */
    public static function normalise(string $type, mixed $input): string
    {
        $type = self::canonical($type);

        if (is_array($input)) {
            return (string) json_encode(
                array_map(static fn ($value): string => (string) $value, $input),
                JSON_UNESCAPED_SLASHES
            );
        }

        $text = trim((string) $input);
        $element = self::elementOf($type);

        if (null !== $element) {
            $out = [];

            foreach (self::listOf($text) ?? [] as $value) {
                $out[] = self::normalise($element[0], $value);
            }

            return (string) json_encode($out, JSON_UNESCAPED_SLASHES);
        }

        if ('address' === $type) {
            return '0x' . strtolower(self::stripPrefix($text));
        }

        if ('bool' === $type) {
            return in_array(strtolower($text), ['true', '1'], true) ? 'true' : 'false';
        }

        if ('bytes' === $type || 1 === preg_match('/^bytes\d+$/', $type)) {
            return '0x' . strtolower(self::stripPrefix($text));
        }

        return $text;
    }

    /**
     * `T[]` and `T[k]` as `[element, count]`, or null when the type is not an
     * array.
     *
     * @param string $type Type.
     *
     * @return array{0:string,1:int}|null
     */
    private static function elementOf(string $type): ?array
    {
        if (1 === preg_match('/^(.+)\[(\d*)\]$/', $type, $matches)) {
            return [$matches[1], '' === $matches[2] ? 0 : (int) $matches[2]];
        }

        return null;
    }

    /**
     * A list from either a JSON array or a comma-separated string.
     *
     * @param string $text Input.
     *
     * @return array|null Null when the text starts like JSON and is not JSON.
     */
    private static function listOf(string $text): ?array
    {
        if ('' === $text) {
            return [];
        }

        if ('[' === substr($text, 0, 1)) {
            $decoded = json_decode($text, true);

            // `null` is the difference between "a broken list" and "an empty
            // list", and only the caller knows which is harmless.
            return is_array($decoded) ? $decoded : null;
        }

        return array_map('trim', explode(',', $text));
    }

    /**
     * Problems of every element of a list.
     *
     * @param string $type Element type.
     * @param array  $list Values.
     *
     * @return Problem|null
     */
    private static function problemArray(string $type, array $list): ?Problem
    {
        if (str_starts_with($type, '[') || null !== self::elementOf($type)) {
            return new Problem('array.nested', [$type]);
        }

        foreach ($list as $index => $value) {
            $problem = self::problem($type, $value);

            if (null !== $problem) {
                return new Problem('array.element', [(int) $index + 1, $problem]);
            }
        }

        return null;
    }

    /**
     * @param string $text Input.
     *
     * @return Problem|null
     */
    private static function problemAddress(string $text): ?Problem
    {
        $hex = self::stripPrefix($text);

        // A bare 40-digit string has to stay an address: an address of only
        // digits, pasted without `0x`, is the shape a numeric parser reads as a
        // number.
        return 1 === preg_match('/^[0-9a-fA-F]{40}$/', $hex)
            ? null
            : new Problem('address.length', [strlen($hex)]);
    }

    /**
     * @param string $text  Input.
     * @param int    $bytes Exact length in bytes, or 0 for any even length.
     *
     * @return Problem|null
     */
    private static function problemBytes(string $text, int $bytes): ?Problem
    {
        $hex = self::stripPrefix($text);

        if ('' !== $hex && 1 !== preg_match('/^[0-9a-fA-F]+$/', $hex)) {
            return new Problem('bytes.hex');
        }

        if (0 !== strlen($hex) % 2) {
            return new Problem('bytes.odd_length');
        }

        if ($bytes > 0 && strlen($hex) !== $bytes * 2) {
            return new Problem('bytes.length', [$bytes, $bytes * 2, strlen($hex)]);
        }

        return null;
    }

    /**
     * @param string $type    Type (`uint…` or `int…`).
     * @param string $text    Input.
     * @param array  $matches The `preg_match` captures.
     *
     * @return Problem|null
     */
    private static function problemInteger(string $type, string $text, array $matches): ?Problem
    {
        $bits = isset($matches[1]) && '' !== $matches[1] ? (int) $matches[1] : 256;
        $signed = str_starts_with($type, 'int');

        if ($bits < 8 || $bits > 256 || 0 !== $bits % 8) {
            return new Problem('int.bad_width', [$type]);
        }

        if ('' === $text) {
            return new Problem('int.not_integer');
        }

        if (1 !== preg_match('/^-?\d+$/', $text)) {
            return new Problem('int.not_integer');
        }

        if (!$signed && '-' === $text[0]) {
            return new Problem('int.negative', [$type]);
        }

        $value = self::unsigned($text);

        if ('' === $value) {
            return null;
        }

        if (!$signed) {
            // `uintM` holds 0 … 2^M - 1.
            return self::compare($value, self::LIMITS[$bits]) >= 0
                ? new Problem('int.too_big', [$type, $text])
                : null;
        }

        // `intM` holds -2^(M-1) … 2^(M-1) - 1: the negative end reaches exactly
        // 2^(M-1) in magnitude, so the comparison differs by the sign.
        $order = self::compare($value, self::LIMITS[$bits - 1]);

        if ('-' === $text[0]) {
            return $order > 0 ? new Problem('int.too_small', [$type, $text]) : null;
        }

        return $order >= 0 ? new Problem('int.too_big', [$type, $text]) : null;
    }

    /**
     * Value without its sign and leading zeros.
     *
     * @param string $text Decimal text.
     *
     * @return string Digits, or '' when they are all zeros.
     */
    private static function unsigned(string $text): string
    {
        $digits = ltrim(str_replace('-', '', $text), '0');

        return '' === $digits ? '0' : $digits;
    }

    /**
     * Compares two decimal strings by value.
     *
     * @param string $left  Digits.
     * @param string $right Digits.
     *
     * @return int -1, 0 or 1.
     */
    private static function compare(string $left, string $right): int
    {
        $left = self::unsigned($left);
        $right = self::unsigned($right);

        if (strlen($left) !== strlen($right)) {
            return strlen($left) < strlen($right) ? -1 : 1;
        }

        $order = strcmp($left, $right);

        return 0 === $order ? 0 : ($order < 0 ? -1 : 1);
    }

    /**
     * @param string $text Input.
     *
     * @return string Hex digits without `0x`.
     */
    private static function stripPrefix(string $text): string
    {
        return str_starts_with(strtolower($text), '0x') ? substr($text, 2) : $text;
    }
}
