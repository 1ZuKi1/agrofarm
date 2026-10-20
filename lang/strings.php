<?php
/**
 * lang/strings.php — every text on the site that exists in more than one
 * language, written once with its English, Mongolian and Chinese versions
 * side by side. Only the home page is multilingual; every other page is
 * Mongolian only and keeps its text in the page itself.
 *
 * Values are HTML: <em>, <strong> and <br> are allowed and survive the
 * language switch. To change a text, edit it here — nowhere else.
 * Chinese copy style (transcreate, don't translate; 蒙古国, no trailing 。 in
 * headlines, space between Latin/digits and Chinese) still applies.
 *
 * Used through inc/i18n.php: ta('key') / tx('key') on the home page, and by
 * inc/layout.php for the shared header, menu and footer.
 */

return [

  // ---- Page title (browser tab) ----
  'home.title' => [
    'en' => 'Nuudelchin Agro Farm LLC — Intensive Livestock Farming & Dairy, Mongolia',
    'mn' => 'Нүүдэлчин Агро Ферм ХХК — Эрчимжсэн мал аж ахуй, сүүний үйлдвэрлэл',
    'zh' => 'Nuudelchin Agro Farm LLC — 蒙古国集约化畜牧与乳业',
  ],

  // ---- Header and menu (every page; Mongolian only outside the home page) ----
  'nav.about' => [
    'en' => 'About',
    'mn' => 'Бидний тухай',
    'zh' => '关于我们',
  ],
  'nav.journey' => [
    'en' => 'Journey',
    'mn' => 'Замнал',
    'zh' => '发展历程',
  ],
  'nav.farms' => [
    'en' => 'Farms',
    'mn' => 'Фермүүд',
    'zh' => '牧场',
  ],
  'nav.services' => [
    'en' => 'Services',
    'mn' => 'Үйлчилгээ',
    'zh' => '服务',
  ],
  'nav.products' => [
    'en' => 'Products',
    'mn' => 'Бүтээгдэхүүн',
    'zh' => '产品',
  ],
  'nav.shop' => [
    'en' => 'Shop',
    'mn' => 'Дэлгүүр',
    'zh' => '商城',
  ],
  'nav.news' => [
    'en' => 'News',
    'mn' => 'Мэдээ',
    'zh' => '新闻',
  ],
  'nav.contact' => [
    'en' => 'Contact',
    'mn' => 'Холбоо барих',
    'zh' => '联系我们',
  ],
  'nav.cta' => [
    'en' => 'Get in touch',
    'mn' => 'Холбогдох',
    'zh' => '洽谈合作',
  ],

  // ---- Accessibility (every page) ----
  'a11y.skip' => [
    'en' => 'Skip to content',
    'mn' => 'Үндсэн агуулга руу шилжих',
    'zh' => '跳到正文',
  ],

  // ---- Footer (every page) ----
  'footer.tagline' => [
    'en' => 'Independent Mongolian dairy company, est. 2015.',
    'mn' => 'Бие даасан Монгол сүүний компани — 2015 оноос.',
    'zh' => '蒙古国独立乳业公司 · 创立于 2015',
  ],

  // ---- Home — Hero ----
  'hero.est-2015' => [
    'en' => 'Нүүдэлчин Агро Ферм ХХК · Est. 2015',
    'mn' => 'Нүүдэлчин Агро Ферм ХХК · 2015 оноос',
    'zh' => 'Nuudelchin Agro Farm LLC · 2015 年创立',
  ],
  'hero.from-the' => [
    'en' => 'From the',
    'mn' => 'Монголын уудам',
    'zh' => '从蒙古草原',
  ],
  'hero.mongolian-steppe' => [
    'en' => 'Mongolian steppe,',
    'mn' => 'тал нутгаас,',
    'zh' => '到万家餐桌',
  ],
  'hero.to-every-table' => [
    'en' => 'to every table.',
    'mn' => 'айл бүрийн ширээнд.',
    'zh' => '每一杯都是好奶',
  ],
  'hero.mongolias-pioneer-of-cluster' => [
    'en' => 'Mongolia\'s pioneer of cluster dairy farming: 1,620 cows across four cluster farms in Töv province, supplying healthy, safe, high-quality milk to the nation.',
    'mn' => 'Монголын кластер сүүний фермийн анхдагч — Төв аймгийн дөрвөн кластер фермд 1,620 үнээ маллаж, эрүүл, аюулгүй, өндөр чанартай сүүг үндэсний үйлдвэрлэлд нийлүүлж байна.',
    'zh' => '蒙古国集群化奶牛养殖的开创者。Töv 省四座集群牧场，1,620 头奶牛，把健康、安全、优质的牛奶送到全国。',
  ],
  'hero.our-journey' => [
    'en' => 'Our journey',
    'mn' => 'Бидний замнал',
    'zh' => '我们的历程',
  ],
  'hero.what-we-do' => [
    'en' => 'What we do',
    'mn' => 'Үйл ажиллагаа',
    'zh' => '业务领域',
  ],

  // ---- Home — Stats band ----
  'stats.cows-across-four-cluster' => [
    'en' => 'Cows across four cluster farms',
    'mn' => 'Дөрвөн кластер фермийн үнээ',
    'zh' => '四座集群牧场存栏',
  ],
  'stats.litres-of-milk-supplied' => [
    'en' => 'Litres of milk supplied to domestic dairies each year',
    'mn' => 'Жил бүр дотоодын үйлдвэрт нийлүүлдэг сүү, литр',
    'zh' => '年供国内乳企（升）',
  ],
  'stats.herder-households-across-the' => [
    'en' => 'Herder households across the clusters',
    'mn' => 'Кластеруудад хамтран ажилладаг малчин өрх',
    'zh' => '参与集群的牧民家庭',
  ],
  'stats.permanent-jobs-created' => [
    'en' => 'Permanent jobs created',
    'mn' => 'Байнгын ажлын байр',
    'zh' => '长期就业岗位',
  ],
  'stats.montbeliarde-heifers-imported' => [
    'en' => 'Montbéliarde heifers imported from France',
    'mn' => 'Франциас импортолсон Монбельярд үнээ',
    'zh' => '自法国引进的蒙贝利亚德牛',
  ],

  // ---- Home — About ----
  'about.who-we-are' => [
    'en' => 'Who we are',
    'mn' => 'Бид хэн бэ',
    'zh' => '企业简介',
  ],
  'about.nomadic-heritage-modern-agriculture' => [
    'en' => 'Nomadic heritage, <em>modern agriculture.</em>',
    'mn' => 'Нүүдэлчин өв уламжлал, <em>орчин үеийн хөдөө аж ахуй.</em>',
    'zh' => '游牧千年，<em>现代牧业</em>',
  ],
  'about.nuudelchin-agro-farm-llc' => [
    'en' => 'Nuudelchin Agro Farm LLC is an independent national company founded in 2015 to modernize Mongolia\'s dairy sector and grow domestic supply by developing cluster household farming. In 2017 we opened Mongolia\'s first 400-cow cluster dairy farm, uniting 10 herder families under one sustainable system. Since then we have built three more clusters in Argalant, Bayankhangai and Bayan soums of Töv province, imported over 450 Montbéliarde cattle from France, and are building a 600-cow green farm complex in Ugtaaltsaidam, due to enter operation in 2027. We are now working to power our farms with green energy, turning cattle manure into energy and organic fertiliser.',
    'mn' => 'Нүүдэлчин Агро Ферм ХХК нь 2015 оноос Монголын сүүний салбарыг шинэчлэх, дотоодын нийлүүлэлтийг нэмэгдүүлэх кластер өрхийн фермийн аж ахуйг хөгжүүлэх зорилготойгоор байгуулагдсан үндэсний бие даасан компани юм. Бид 2017 онд Монголын анхны 400 үнээний кластер сүүний фермийг нээж, 10 малчин өрхийг нэг тогтвортой системд нэгтгэсэн. Түүнээс хойш Төв аймгийн Аргалант, Баянхангай, Баян сумдад гурван кластерыг амжилттай хэрэгжүүлж, Францаас 450 гаруй Монбелиард үхэр импортолж, Төв аймгийн Угтаалцайдам суманд 600 үнээний цогцолбор кластерын ногоон фермийг барьж, 2027 онд ашиглалтад оруулахаар ажиллаж байна. Цаашид фермийн аж ахуйгаа байгальд ээлтэй ногоон эрчим хүчээр хангаж, малын бууц шивхийг эрчим хүч, органик бордоо болгох чиглэлд ажиллаж байна.',
    'zh' => 'Nuudelchin Agro Farm LLC 创立于 2015 年，是蒙古国的独立民族企业，以集群化家庭牧场推动本国乳业升级、提升国内供应。2017 年建成蒙古国第一座 400 头规模的集群奶牛场，把 10 户牧民家庭纳入同一套可持续体系。此后在 Töv 省的 Argalant、Bayankhangai、Bayan 三地陆续建成集群牧场，自法国引进 450 余头蒙贝利亚德牛；Ugtaaltsaidam 600 头规模的绿色牧场综合体正在建设，计划 2027 年投产。下一步，是让牧场用上绿色能源，把牛粪转化为能源和有机肥。',
  ],
  'about.mission' => [
    'en' => 'Mission',
    'mn' => 'Эрхэм зорилго',
    'zh' => '使命',
  ],
  'about.to-develop-farming-intensive' => [
    'en' => 'To develop farming, intensive livestock husbandry and crop production, bring their resources together and, working closely with professional institutions, be the national company that provides complete farm services.',
    'mn' => 'Фермийн аж ахуй, эрчимжсэн мал аж ахуй, газар тариаланг хөгжүүлж, тэдгээрийн нөөцийг нэгтгэн, мэргэжлийн байгууллагуудтай нягт хамтран ажиллаж, фермийн цогц үйлчилгээ үзүүлэгч үндэсний компани байх.',
    'zh' => '发展农场经营、集约化畜牧与种植，整合三者资源，携手专业机构，做能提供牧场全链条服务的民族企业。',
  ],
  'about.vision' => [
    'en' => 'Vision',
    'mn' => 'Алсын хараа',
    'zh' => '愿景',
  ],
  'about.a-solution-for-rural' => [
    'en' => 'A solution for rural development: building intensive farming at home.',
    'mn' => 'Хөдөөгийн хөгжлийн шийдэл — эх орондоо эрчимжсэн аж ахуйг хөгжүүлнэ.',
    'zh' => '乡村发展的答案：在自己的土地上，办集约化牧业。',
  ],
  'about.to-bring-mongolias-dairy' => [
    'en' => 'To bring Mongolia\'s dairy sector to international standards by producing healthy, safe, and high-quality raw materials that protect public health.',
    'mn' => 'Эрүүл, аюулгүй, өндөр чанартай түүхий эд үйлдвэрлэн нийтийн эрүүл мэндийг хамгаалж, Монголын сүүний салбарыг олон улсын түвшинд хүргэх.',
    'zh' => '生产健康、安全、优质的原料奶，守护公众健康，把蒙古国乳业带上国际水准。',
  ],
  'about.first-cluster-farm-opening' => [
    'en' => 'Opening of the first cluster farm in Bayandelger, Töv province, 2017',
    'mn' => 'Анхны кластер фермийн нээлт — Баяндэлгэр, Төв аймаг, 2017',
    'zh' => '2017 年，首座集群牧场在 Töv 省 Bayandelger 开业',
  ],
  'about.first-cluster-farm-in' => [
    'en' => 'First cluster farm in Mongolia',
    'mn' => 'Монголын анхны кластер ферм',
    'zh' => '蒙古国首座集群牧场',
  ],

  // ---- Home — Journey (timeline) ----
  'journey.a-journey-measured-in' => [
    'en' => 'A journey measured <em>in herds, not years.</em>',
    'mn' => 'Он жилээр бус, <em>сүргээр хэмжигдэх замнал.</em>',
    'zh' => '不以年月计，<em>以畜群丈量</em>',
  ],
  'journey.before-the-company' => [
    'en' => 'Before the company',
    'mn' => 'Компани байгуулагдахаас өмнө',
    'zh' => '创业之前',
  ],
  'journey.french-bloodlines' => [
    'en' => 'French bloodlines',
    'mn' => 'Францын үүлдэр',
    'zh' => '法国血统',
  ],
  'journey.montbeliarde-heifers-imported' => [
    'en' => 'Montbéliarde heifers imported from France by air and land transport, the groundwork for everything that followed.',
    'mn' => 'Франциас Монбельярд үүлдрийн өсвөр үнээг агаарын болон газрын тээврээр импортлов — цаашдын бүхий л ажлын суурь тавигдав.',
    'zh' => '自法国空运、陆运引进蒙贝利亚德育成牛，为日后的一切打下底子。',
  ],
  'journey.the-company-is-founded' => [
    'en' => 'The company is founded',
    'mn' => 'Компани байгуулагдав',
    'zh' => '公司成立',
  ],
  'journey.nuudelchin-agro-farm-llc' => [
    'en' => 'Nuudelchin Agro Farm LLC is established as an independent national company, set up to modernize Mongolia\'s dairy sector and grow domestic supply through cluster household farming.',
    'mn' => 'Нүүдэлчин Агро Ферм ХХК нь кластер өрхийн фермийн аж ахуйг хөгжүүлэн Монголын сүүний салбарыг шинэчлэх, дотоодын нийлүүлэлтийг нэмэгдүүлэх зорилготой үндэсний бие даасан компани болон байгуулагдав.',
    'zh' => 'Nuudelchin Agro Farm LLC 成立，定位为独立民族企业，以集群化家庭牧场推动蒙古国乳业升级、提升国内供应。',
  ],
  'journey.on-the-national-stage' => [
    'en' => 'On the national stage',
    'mn' => 'Үндэсний индэр дээр',
    'zh' => '走上国家舞台',
  ],
  'journey.director-j-battugs-speaks' => [
    'en' => 'Director J. Battugs speaks at the Mongolia Economic Forum on short- and long-term solutions to increase exports.',
    'mn' => 'Захирал Ж.Баттөгс Монголын эдийн засгийн чуулган дээр экспортыг нэмэгдүүлэх богино болон урт хугацааны шийдлийн талаар илтгэл тавив.',
    'zh' => '总经理巴特图格斯在蒙古国经济论坛发言，谈扩大出口的长短期路径。',
  ],
  'journey.first-cluster-farm' => [
    'en' => 'First cluster farm',
    'mn' => 'Анхны кластер ферм',
    'zh' => '首座集群牧场',
  ],
  'journey.mongolias-first-400-cow' => [
    'en' => 'Mongolia\'s first 400-cow cluster dairy farm opens in Bayandelger soum, Töv province: 10 herder families, 40 cows each.',
    'mn' => 'Төв аймгийн Баяндэлгэр суманд Монголын анхны 400 үнээний кластер сүүний ферм нээгдэв — 10 малчин өрх, тус бүр 40 үнээ.',
    'zh' => '蒙古国第一座 400 头规模的集群奶牛场在 Töv 省 Bayandelger 苏木开业：10 户牧民家庭，每户 40 头。',
  ],
  'journey.growing-the-herd' => [
    'en' => 'Growing the herd',
    'mn' => 'Сүрэг өсөв',
    'zh' => '畜群壮大',
  ],
  'journey.france-mongolia-animal-husbandry' => [
    'en' => 'France–Mongolia animal husbandry cooperation seminar; further Montbéliarde imports bring the total to 450+. Second cluster farm: 480 cows in Argalant soum.',
    'mn' => 'Франц–Монголын мал аж ахуйн хамтын ажиллагааны семинар; нэмэлт импортоор Монбельярд үнээ 450+ болов. Хоёр дахь кластер ферм — Аргалант суманд 480 үнээ.',
    'zh' => '法国—蒙古国畜牧合作研讨会举行；追加引进后，蒙贝利亚德牛存栏突破 450 头。第二座集群牧场落地 Argalant 苏木，480 头。',
  ],
  'journey.third-cluster' => [
    'en' => 'Third cluster',
    'mn' => 'Гурав дахь кластер',
    'zh' => '第三座集群',
  ],
  'journey.240-cow-cluster-farm' => [
    'en' => '240-cow cluster farm established in Bayankhangai soum, Töv province.',
    'mn' => 'Төв аймгийн Баянхангай суманд 240 үнээний кластер ферм байгуулагдав.',
    'zh' => 'Töv 省 Bayankhangai 苏木建成 240 头规模的集群牧场。',
  ],
  'journey.presidential-visit' => [
    'en' => 'Presidential visit',
    'mn' => 'Ерөнхийлөгчийн айлчлал',
    'zh' => '总统到访',
  ],
  'journey.h-e-khurelsukh-ukhnaa' => [
    'en' => 'H.E. Khurelsukh Ukhnaa, President of Mongolia, visits our operations on December 10 within the "Food Supply and Security" National Movement.',
    'mn' => 'Монгол Улсын Ерөнхийлөгч У.Хүрэлсүх 12 дугаар сарын 10-нд «Хүнсний хангамж, аюулгүй байдал» үндэсний хөдөлгөөний хүрээнд манай үйл ажиллагаатай танилцав.',
    'zh' => '12 月 10 日，蒙古国总统乌·呼日勒苏赫在“食品供应与安全”国家运动框架下考察牧场。',
  ],
  'journey.fourth-cluster-first-holsteins' => [
    'en' => 'Fourth cluster, first Holsteins',
    'mn' => 'Дөрөв дэх кластер, анхны Холштейн',
    'zh' => '第四座集群，首批荷斯坦',
  ],
  'journey.the-500-cow-bayan' => [
    'en' => 'The 500-cow Bayan cluster, our largest to date, joins the group, and 400 Holstein cattle are imported from China, a first for Mongolia.',
    'mn' => '500 үнээний Баян кластер — өнөөг хүртэлх хамгийн том нь — нэгдэж, Хятадаас 400 Холштейн үхэр импортлов. Энэ нь Монголд анх удаа.',
    'zh' => '500 头规模的 Bayan 集群加入，成为迄今最大的一座；同年自中国引进 400 头荷斯坦牛，这在蒙古国尚属首次。',
  ],
  'journey.the-green-complex-opens' => [
    'en' => 'The green complex opens',
    'mn' => 'Ногоон цогцолбор ашиглалтад',
    'zh' => '绿色综合体投产',
  ],
  'journey.the-600-cow-green' => [
    'en' => 'The 600-cow green farm complex in Ugtaaltsaidam soum enters operation. It is the largest project in the company\'s history, and the first designed around renewable energy from the outset.',
    'mn' => 'Угтаалцайдам суманд 600 үнээний цогцолбор кластерын ногоон ферм ашиглалтад орно — компанийн түүхэн дэх хамгийн том төсөл бөгөөд сэргээгдэх эрчим хүчийг анхнаас нь тооцож төлөвлөсөн анхны ферм.',
    'zh' => 'Ugtaaltsaidam 600 头规模的绿色牧场综合体投产：公司史上最大的项目，也是第一座从设计之初就围绕可再生能源规划的牧场。',
  ],
  'journey.the-select-herd' => [
    'en' => 'The select herd',
    'mn' => 'Шилмэл сүрэг',
    'zh' => '良种核心群',
  ],
  'journey.target-import-1-000' => [
    'en' => 'Target: import 1,000 purebred cows and create a national select herd with government support.',
    'mn' => 'Зорилт: 1,000 цэвэр үүлдрийн үнээ импортолж, төрийн дэмжлэгтэйгээр үндэсний шилмэл сүрэг бүрдүүлэх.',
    'zh' => '目标：引进 1,000 头纯种母牛，在国家支持下建成全国良种核心群。',
  ],

  // ---- Home — Green energy ----
  'green.from-the-barn-floor' => [
    'en' => 'From the barn floor <em>to the grid.</em>',
    'mn' => 'Хашааны бууцнаас <em>эрчим хүч рүү.</em>',
    'zh' => '从圈舍粪污，<em>到清洁能源</em>',
  ],
  'green.the-next-step-is' => [
    'en' => 'The next step is to power the farms with renewable energy. Cattle manure is being turned into energy and organic fertiliser, returning nutrients to the fodder fields they came from and leaving the steppe as we found it.',
    'mn' => 'Дараагийн алхам бол фермүүдээ сэргээгдэх эрчим хүчээр хангах явдал. Малын бууц шивхийг эрчим хүч, органик бордоо болгож, тэжээлийн талбайд нь шимийг нь буцааж, тал нутгаа байсан хэвээр нь үлдээхийн төлөө ажиллаж байна.',
    'zh' => '下一步，是让牧场用上可再生能源。牛粪转化为能源和有机肥，养分回到出产它的饲草料地，草原还是原来的草原。',
  ],
  'green.energy-from-manure' => [
    'en' => 'Energy from manure',
    'mn' => 'Бууцнаас эрчим хүч',
    'zh' => '变粪为能',
  ],
  'green.together-we-are-solving' => [
    'en' => 'Together we are solving the processing of cattle manure into renewable energy for the farm\'s own use.',
    'mn' => 'Малын бууц шивхийг боловсруулж, фермийн өөрийн хэрэгцээний сэргээгдэх эрчим хүч болгон ашиглах төслийг хамтдаа шийднэ.',
    'zh' => '把牛粪处理成牧场自用的可再生能源，这件事我们一起来解决。',
  ],
  'green.organic-fertiliser' => [
    'en' => 'Organic fertiliser',
    'mn' => 'Органик бордоо',
    'zh' => '有机肥',
  ],
  'green.cattle-slurry-is-processed' => [
    'en' => 'Cattle slurry is processed into organic fertiliser, a green project to protect and restore the fodder fields and the degraded pasture around them.',
    'mn' => 'Үхрийн шивхийг боловсруулан органик бордоо үйлдвэрлэж, тэжээлийн талбай, доройтсон бэлчээрийг хамгаалах, сэргээх ногоон төсөл хэрэгжүүлнэ.',
    'zh' => '牛粪浆制成有机肥，用来保护和修复饲草料地，以及周边退化的草场。',
  ],
  'green.organic-farm' => [
    'en' => 'Organic farm',
    'mn' => 'Органик ферм',
    'zh' => '有机牧场',
  ],
  'green.at-ugtaaltsaidam-fodder-fertiliser' => [
    'en' => 'At Ugtaaltsaidam, fodder, fertiliser and crop growing run on eco-friendly, circular, zero-waste technology. The farm is developing into a certified organic farm producing organic milk and dairy products.',
    'mn' => 'Угтаалцайдам төсөлд тэжээл, бордоо, тариаланд байгальд ээлтэй, дахин ашиглагдах, хаягдалгүй технологи нэвтрүүлж, органик баталгаажуулалтад хамрагдсан органик сүү, сүүн бүтээгдэхүүн үйлдвэрлэх онцлог төсөл болон хөгжинө.',
    'zh' => '在 Ugtaaltsaidam，饲草料、肥料与种植全部采用环保、循环、零废弃的技术，目标是通过有机认证，产出有机牛奶与乳制品。',
  ],

  // ---- Home — Farms ----
  'farms.bayandelger' => [
    'en' => 'Bayandelger',
    'mn' => 'Баяндэлгэр',
    'zh' => 'Bayandelger',
  ],
  'farms.established-2017-mongolias-first' => [
    'en' => 'Established 2017 · Mongolia\'s first cluster dairy farm',
    'mn' => '2017 онд байгуулагдсан · Монголын анхны кластер ферм',
    'zh' => '2017 年建成 · 蒙古国首座集群奶牛场',
  ],
  'farms.argalant' => [
    'en' => 'Argalant',
    'mn' => 'Аргалант',
    'zh' => 'Argalant',
  ],
  'farms.established-2020-second-cluster' => [
    'en' => 'Established 2020 · Second cluster farm',
    'mn' => '2020 онд байгуулагдсан · Хоёр дахь кластер ферм',
    'zh' => '2020 年建成 · 第二座集群牧场',
  ],
  'farms.bayankhangai' => [
    'en' => 'Bayankhangai',
    'mn' => 'Баянхангай',
    'zh' => 'Bayankhangai',
  ],
  'farms.established-2023-third-cluster' => [
    'en' => 'Established 2023 · Third cluster farm',
    'mn' => '2023 онд байгуулагдсан · Гурав дахь кластер ферм',
    'zh' => '2023 年建成 · 第三座集群牧场',
  ],
  'farms.bayan' => [
    'en' => 'Bayan',
    'mn' => 'Баян',
    'zh' => 'Bayan',
  ],
  'farms.established-2025-our-largest' => [
    'en' => 'Established 2025 · Our largest cluster',
    'mn' => '2025 онд байгуулагдсан · Хамгийн том кластер',
    'zh' => '2025 年建成 · 规模最大的集群',
  ],
  'farms.four-clusters-one-steppe' => [
    'en' => 'Four clusters, <em>one steppe.</em>',
    'mn' => 'Дөрвөн кластер, <em>нэг тал нутаг.</em>',
    'zh' => '四座集群，<em>一片草原</em>',
  ],
  'farms.each-cluster-farm-unites' => [
    'en' => 'Each cluster farm unites 10 herder households around shared infrastructure, veterinary care, and a guaranteed milk market. Every family works 10 hectares of its own land (2 with housing and green landscaping, 8 for pasture and fodder), and together the clusters supply 4–5 million litres of milk to domestic dairy factories every year.',
    'mn' => 'Кластер ферм бүр 10 малчин өрхийг дундын дэд бүтэц, мал эмнэлгийн үйлчилгээ, баталгаат сүүний зах зээлээр нэгтгэдэг. Өрх бүр өөрийн 10 га газартай — 2 га-д ногоон байгууламж бүхий орон сууц, 8 га-г бэлчээр, тэжээлийн тариалалтад ашигладаг. Кластерууд жил бүр 4–5 сая литр сүүг дотоодын сүүний үйлдвэрүүдэд нийлүүлдэг.',
    'zh' => '每座集群牧场把 10 户牧民家庭聚在一起，共用基础设施、兽医服务和有保障的收奶渠道。每户经营自有的 10 公顷土地：2 公顷是住房和绿化，8 公顷是草场与饲草料。四座集群每年向国内乳企供应 400–500 万升牛奶。',
  ],
  'farms.architects-visualization' => [
    'en' => 'Architect\'s visualization',
    'mn' => 'Төслийн зураглал',
    'zh' => '项目效果图',
  ],
  'farms.ugtaaltsaidam' => [
    'en' => 'Ugtaaltsaidam',
    'mn' => 'Угтаалцайдам',
    'zh' => 'Ugtaaltsaidam',
  ],
  'farms.a-600-cow-green' => [
    'en' => 'A 600-cow green cluster farm complex rising in Ugtaaltsaidam soum, working towards entering operation in 2027. The project solves renewable energy, organic fertiliser, a shared milking parlour and fodder cultivation together, in one design.',
    'mn' => 'Угтаалцайдам суманд 600 үнээний цогцолбор кластерын ногоон фермийг 2027 онд ашиглалтад оруулахаар ажиллаж байна. Сэргээгдэх эрчим хүч, органик бордоо, нэгдсэн саалтуур, тэжээлийн тариалалтыг цогцоор нь шийдсэн онцлог төсөл.',
    'zh' => '600 头规模的绿色集群牧场综合体正在 Ugtaaltsaidam 苏木建设，力争 2027 年投产。可再生能源、有机肥、共用挤奶厅、饲草料种植，一套设计一并解决。',
  ],
  'farms.the-project-opens-the' => [
    'en' => 'The project opens the opportunity to own 20, 40 or 100 cows and become a full participant. Let us build this cluster farm complex together.',
    'mn' => 'Тус төсөлд 20 үнээ, 40 үнээ, 100 үнээ эзэмшин төслийн оролцогч болох бүрэн боломжтой — цогцолбор кластерын фермийг хамтдаа бүтээцгээе.',
    'zh' => '持有 20 头、40 头或 100 头奶牛，即可成为项目的正式参与者。这座集群牧场，我们一起建。',
  ],
  'farms.cows' => [
    'en' => 'Cows',
    'mn' => 'Үнээ',
    'zh' => '奶牛',
  ],
  'farms.enters-operation' => [
    'en' => 'Enters operation',
    'mn' => 'Ашиглалтад орох',
    'zh' => '投产',
  ],
  'farms.t-v' => [
    'en' => 'Töv',
    'mn' => 'Төв',
    'zh' => 'Töv 省',
  ],
  'farms.ugtaaltsaidam-soum' => [
    'en' => 'Ugtaaltsaidam soum',
    'mn' => 'Угтаалцайдам сум',
    'zh' => 'Ugtaaltsaidam 苏木',
  ],
  'farms.become-a-participant' => [
    'en' => 'Become a participant →',
    'mn' => 'Оролцогч болох →',
    'zh' => '了解参与方式 →',
  ],

  // ---- Home — Services ----
  'services.from-blueprint-to-bottling' => [
    'en' => 'From blueprint <em>to bottling.</em>',
    'mn' => 'Зураг төслөөс <em>савлагаа хүртэл.</em>',
    'zh' => '从一纸蓝图，<em>到一瓶好奶</em>',
  ],
  'services.farm-construction' => [
    'en' => 'Farm Construction',
    'mn' => 'Ферм барилга',
    'zh' => '牧场建设',
  ],
  'services.planning-engineering-infrastructure-and' => [
    'en' => 'Planning, engineering, infrastructure, and equipment for modern dairy farms.',
    'mn' => 'Орчин үеийн сүүний фермийн төлөвлөлт, инженерчлэл, дэд бүтэц, тоног төхөөрөмж.',
    'zh' => '现代化奶牛场的规划、工程、基础设施与设备。',
  ],
  'services.consulting' => [
    'en' => 'Consulting',
    'mn' => 'Зөвлөх үйлчилгээ',
    'zh' => '咨询服务',
  ],
  'services.business-plans-investment-models' => [
    'en' => 'Business plans, investment models, herd management, and production technology.',
    'mn' => 'Бизнес төлөвлөгөө, хөрөнгө оруулалтын загвар, сүргийн менежмент, үйлдвэрлэлийн технологи.',
    'zh' => '商业计划、投资测算、畜群管理与生产工艺。',
  ],
  'services.training' => [
    'en' => 'Training',
    'mn' => 'Сургалт',
    'zh' => '培训',
  ],
  'services.professional-courses-and-hands' => [
    'en' => 'Professional courses and hands-on programs for farmers and herders.',
    'mn' => 'Фермер, малчдад зориулсан мэргэжлийн болон дадлага сургалтын хөтөлбөрүүд.',
    'zh' => '面向农场主和牧民的专业课程与实操培训。',
  ],
  'services.feed-nutrition' => [
    'en' => 'Feed & Nutrition',
    'mn' => 'Тэжээл, шим тэжээл',
    'zh' => '饲料与营养',
  ],
  'services.formulation-pasture-planning-and' => [
    'en' => 'Formulation, pasture planning, and herd health support.',
    'mn' => 'Тэжээлийн жор, бэлчээрийн төлөвлөлт, сүргийн эрүүл мэндийн дэмжлэг.',
    'zh' => '饲料配方、草场规划、畜群健康。',
  ],
  'services.milk-processing' => [
    'en' => 'Milk Processing',
    'mn' => 'Сүү боловсруулалт',
    'zh' => '乳品加工',
  ],
  'services.collection-cooling-processing-lines' => [
    'en' => 'Collection, cooling, processing lines, quality control, and standards.',
    'mn' => 'Сүү цуглуулалт, хөргөлт, боловсруулах шугам, чанарын хяналт, стандарт.',
    'zh' => '收奶、冷却、加工产线、质量控制与标准体系。',
  ],
  'services.value-chain-development' => [
    'en' => 'Value Chain Development',
    'mn' => 'Үнэ цэнийн сүлжээний хөгжил',
    'zh' => '价值链建设',
  ],
  'services.connecting-production-to-market' => [
    'en' => 'Connecting production to market for sustainable income.',
    'mn' => 'Үйлдвэрлэлийг зах зээлтэй холбож, тогтвортой орлого бий болгоно.',
    'zh' => '打通生产与市场，让收入稳下来。',
  ],

  // ---- Home — Products ----
  'products.what-the-steppe-provides' => [
    'en' => 'What the steppe <em>provides.</em>',
    'mn' => 'Тал нутгийн <em>өгөөж.</em>',
    'zh' => '草原的<em>馈赠</em>',
  ],
  'products.farm-equipment' => [
    'en' => 'Farm Equipment',
    'mn' => 'Фермийн тоног төхөөрөмж',
    'zh' => '牧场设备',
  ],
  'products.milking-machines-cooling-tanks' => [
    'en' => 'Milking machines, cooling tanks, feeders, and complete farm equipment.',
    'mn' => 'Саалтуур, хөргөлтийн танк, тэжээгч болон фермийн иж бүрэн тоног төхөөрөмж.',
    'zh' => '挤奶机、冷藏罐、饲喂设备，以及成套牧场装备。',
  ],
  'products.raw-milk-dairy' => [
    'en' => 'Raw Milk & Dairy',
    'mn' => 'Түүхий сүү, сүүн бүтээгдэхүүн',
    'zh' => '生鲜乳与乳制品',
  ],
  'products.healthy-safe-high-quality' => [
    'en' => 'Healthy, safe, high-quality raw milk and processed dairy products.',
    'mn' => 'Эрүүл, аюулгүй, өндөр чанартай түүхий сүү, боловсруулсан сүүн бүтээгдэхүүн.',
    'zh' => '健康、安全、优质的生鲜乳，以及加工乳制品。',
  ],
  'products.breeding-livestock' => [
    'en' => 'Breeding Livestock',
    'mn' => 'Үржлийн мал',
    'zh' => '种畜',
  ],
  'products.montbeliarde-breed-from' => [
    'en' => 'Montbéliarde breed from France: 8,520 kg annual milk yield, 3.50% protein, 3.95% fat. Holstein cattle from China, and high-yield pregnant heifers for farm expansion.',
    'mn' => 'Францын Монбельярд үүлдэр — жилд 8,520 кг сүү, уураг 3.50%, тослог 3.95%. Хятадаас импортолсон Холштейн үхэр, өндөр ашиг шимт хээлтэй өсвөр үнээ.',
    'zh' => '法国蒙贝利亚德牛：年产奶 8,520 公斤，蛋白 3.50%，乳脂 3.95%。另有自中国引进的荷斯坦牛，以及适合扩群的高产妊娠青年母牛。',
  ],
  'products.shop-cta' => [
    'en' => 'Visit the shop',
    'mn' => 'Дэлгүүр орох',
    'zh' => '进入商店',
  ],
  'products.erdest-doloots' => [
    'en' => 'Mineral Lick Block',
    'mn' => 'Эрдэст Долооц',
    'zh' => '矿物质舔砖',
  ],
  'products.mineral-lick-block-for' => [
    'en' => 'Mineral lick block for livestock.',
    'mn' => 'Малын эрдэст долоолуур.',
    'zh' => '牲畜用矿物质舔砖。',
  ],

  // ---- Home — Recognition ----
  'recognition.a-national-movement-a' => [
    'en' => 'A national movement, <em>a presidential visit.</em>',
    'mn' => 'Үндэсний хөдөлгөөн, <em>Ерөнхийлөгчийн айлчлал.</em>',
    'zh' => '一场国家运动，<em>一次总统到访</em>',
  ],
  'recognition.on-december-10-2024' => [
    'en' => 'On December 10, 2024, His Excellency <strong>Khurelsukh Ukhnaa</strong>, President of Mongolia, visited Nuudelchin Agro Farm\'s operations within the framework of the <em>"Food Supply and Security"</em> National Movement, and was briefed on our achievements, milk production, and plans for future expansion.',
    'mn' => '2024 оны 12 дугаар сарын 10-нд Монгол Улсын Ерөнхийлөгч <strong>У.Хүрэлсүх</strong> «Хүнсний хангамж, аюулгүй байдал» үндэсний хөдөлгөөний хүрээнд Нүүдэлчин Агро Фермийн үйл ажиллагаатай танилцаж, компанийн амжилт, сүүний үйлдвэрлэл, өргөтгөлийн төлөвлөгөөний талаар мэдээлэл авав.',
    'zh' => '2024 年 12 月 10 日，蒙古国总统<strong>乌·呼日勒苏赫</strong>在<em>“食品供应与安全”</em>国家运动框架下到访 Nuudelchin Agro Farm，听取公司成绩、牛奶生产和后续扩建计划。',
  ],
  'recognition.the-presidential-delegation-touring' => [
    'en' => 'The presidential delegation touring the farm, 10 December 2024',
    'mn' => 'Ерөнхийлөгчийн айлчлалын үеэр фермийн үйл ажиллагаатай танилцав — 2024 оны 12 дугаар сарын 10',
    'zh' => '总统一行考察牧场 · 2024 年 12 月 10 日',
  ],
  'recognition.if-this-incentive-is' => [
    'en' => '"If this incentive is provided throughout the year, it will be a strong impetus for herder cooperatives to expand their operation."',
    'mn' => '«Энэ урамшууллыг жилийн турш олгодог бол малчдын хоршоод үйл ажиллагаагаа өргөжүүлэхэд хүчтэй түлхэц болно.»',
    'zh' => '“这项补贴若能全年发放，牧民合作社扩大经营就有了底气。”',
  ],
  'recognition.j-battugs-director-nuudelchin' => [
    'en' => '— <strong>J. Battugs</strong>, Director, Nuudelchin Agro Farm LLC',
    'mn' => '— <strong>Ж.Баттөгс</strong>, Захирал, Нүүдэлчин Агро Ферм ХХК',
    'zh' => '— <strong>巴特图格斯</strong>，Nuudelchin Agro Farm LLC 总经理',
  ],
  'recognition.we-aim-to-import' => [
    'en' => '"We aim to import 1,000 purebred cows for milk and meat, and request government support for creating a select herd by 2030."',
    'mn' => '«Сүү, махны чиглэлийн 1,000 цэвэр үүлдрийн үнээ импортолж, 2030 он гэхэд шилмэл сүрэг бүрдүүлэхэд төрийн дэмжлэг хүсэж байна.»',
    'zh' => '“我们计划引进 1,000 头奶用、肉用纯种母牛，也希望获得国家支持，在 2030 年前建成良种核心群。”',
  ],
  'recognition.a-herders-story' => [
    'en' => 'A herder\'s story',
    'mn' => 'Малчны түүх',
    'zh' => '一位牧民的故事',
  ],
  'recognition.the-family-of-b' => [
    'en' => 'The family of <strong>B. Orgil</strong> has farmed with us since 2024, with 18 cows and an average of 18 liters of milk sold per day. Orgil notes that the Government\'s milk incentive of MNT 1,000 per liter greatly supports farmers.',
    'mn' => '<strong>Б.Оргилын</strong> гэр бүл 2024 оноос манайтай хамтран фермерийн үйл ажиллагаа эрхэлж байна — 18 үнээтэй, өдөрт дунджаар 18 литр сүү борлуулдаг. Засгийн газрын литр тутамд 1,000 төгрөгийн сүүний урамшуулал фермерүүдэд ихээхэн дэмжлэг болдгийг Оргил онцолжээ.',
    'zh' => '<strong>奥尔吉勒</strong>一家从 2024 年起加入我们的牧场，养 18 头奶牛，平均每天卖出 18 升奶。他说，政府每升 1,000 图格里克的补贴，对农场主是实打实的帮助。',
  ],
  'recognition.source-montsame-news-agency' => [
    'en' => 'Source: MONTSAME News Agency',
    'mn' => 'Эх сурвалж: МОНЦАМЭ агентлаг',
    'zh' => '来源：蒙通社',
  ],
  'recognition.partners' => [
    'en' => 'Partners',
    'mn' => 'Түншүүд',
    'zh' => '合作伙伴',
  ],
  'recognition.khan-bank' => [
    'en' => 'Khan Bank',
    'mn' => 'ХААН Банк',
    'zh' => '汗银行',
  ],
  'recognition.financing-partner-for-cluster' => [
    'en' => 'Financing partner for cluster farm projects',
    'mn' => 'Кластер фермийн төслийн санхүүжилтийн түнш',
    'zh' => '集群牧场项目融资伙伴',
  ],
  'recognition.french-government' => [
    'en' => 'French Government',
    'mn' => 'Францын Засгийн газар',
    'zh' => '法国政府',
  ],
  'recognition.animal-husbandry-cooperation' => [
    'en' => 'Animal husbandry cooperation',
    'mn' => 'Мал аж ахуйн хамтын ажиллагаа',
    'zh' => '畜牧业合作',
  ],
  'recognition.mongolian-government' => [
    'en' => 'Mongolian Government',
    'mn' => 'Монгол Улсын Засгийн газар',
    'zh' => '蒙古国政府',
  ],
  'recognition.food-supply-and-security' => [
    'en' => '"Food Supply and Security" National Movement',
    'mn' => '«Хүнсний хангамж, аюулгүй байдал» үндэсний хөдөлгөөн',
    'zh' => '“食品供应与安全”国家运动',
  ],
  'recognition.evajura' => [
    'en' => 'EVAJURA',
    'mn' => 'EVAJURA',
    'zh' => 'EVAJURA',
  ],
  'recognition.french-supplier-of-montbeliarde' => [
    'en' => 'French supplier of Montbéliarde breeding cattle',
    'mn' => 'Францын Монбелиард үүлдрийн малын нийлүүлэгч',
    'zh' => '法国蒙贝利亚德种牛供应商',
  ],
  'recognition.coopex' => [
    'en' => 'COOPEX',
    'mn' => 'COOPEX',
    'zh' => 'COOPEX',
  ],
  'recognition.french-breeding-cattle-export' => [
    'en' => 'French breeding-cattle export cooperative',
    'mn' => 'Францын үүлдрийн мал экспортлогч хоршоо',
    'zh' => '法国种牛出口合作社',
  ],

  // ---- Home — News ----
  'news.news-updates' => [
    'en' => 'News & <em>Updates</em>',
    'mn' => 'Мэдээ & <em>Мэдээлэл</em>',
    'zh' => '新闻与<em>动态</em>',
  ],
  'news.see-all-news' => [
    'en' => 'See all news →',
    'mn' => 'Бүх мэдээг үзэх →',
    'zh' => '查看全部新闻 →',
  ],

  // ---- Home — Contact ----
  'contact.lets-build-the-next' => [
    'en' => 'Let\'s build the next <em>cluster together.</em>',
    'mn' => 'Дараагийн кластерыг <em>хамтдаа байгуулъя.</em>',
    'zh' => '共建<em>下一座集群</em>',
  ],
  'contact.rural-development-solution' => [
    'en' => '«Хөдөөгийн хөгжлийн шийдэл»: a rural development solution.',
    'mn' => '«Хөдөөгийн хөгжлийн шийдэл»',
    'zh' => '“乡村发展的答案”',
  ],
  'contact.email' => [
    'en' => 'Email',
    'mn' => 'И-мэйл',
    'zh' => '邮箱',
  ],
  'contact.phone' => [
    'en' => 'Phone',
    'mn' => 'Утас',
    'zh' => '电话',
  ],
  'contact.location' => [
    'en' => 'Location',
    'mn' => 'Байршил',
    'zh' => '地址',
  ],
  'contact.room-402-tsoma-office' => [
    'en' => 'Room 402, Tsoma Office building, Sukhbaatar District, Ulaanbaatar, Mongolia ',
    'mn' => 'Цома Оффис байр, 402 тоот, Сүхбаатар дүүрэг, Улаанбаатар, Монгол',
    'zh' => '蒙古国 Ulaanbaatar 市 Sukhbaatar 区 Tsoma 写字楼 402 室',
  ],

  // ---- Home — Mobile action bar ----
  'mobilebar.call' => [
    'en' => 'Call',
    'mn' => 'Залгах',
    'zh' => '拨打电话',
  ],
  'mobilebar.get-a-quote' => [
    'en' => 'Get a quote',
    'mn' => 'Үнийн санал авах',
    'zh' => '索取报价',
  ],
];
