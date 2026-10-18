<?php
// 法律页面 + Cookie 横幅额外文案（简体中文）
return [
  'meta' => [
    'privacy' => [
      'title' => '隐私政策',
      'description' => '我们如何在您浏览网站以及预订时处理您的个人数据。'
    ],
    'cookies' => [
      'title' => 'Cookie 政策',
      'description' => '关于本网站使用的 Cookie 以及如何管理偏好设置的说明。'
    ],
  ],

  'footer' => [
    'privacy' => '隐私',
    'cookies' => 'Cookie',
    'manage_cookies' => '管理 Cookie',
  ],

  'cookies' => [
    'banner_title' => 'Cookie',
    'banner_text' => '我们使用网站运行所必需的 Cookie。征得您的同意后，我们也可能使用可选的 Cookie（例如分析）来改善体验。您可以随时更改选择。',
    'banner_accept' => '接受',
    'banner_reject' => '拒绝',
    'banner_settings' => '偏好设置',

    'modal_title' => 'Cookie 偏好设置',
    'modal_intro' => '请选择允许的 Cookie 类型。必需 Cookie 始终启用，因为它们对网站正常运行至关重要。',

    'necessary_title' => '必需',
    'necessary_desc' => '网站运行所必需（例如安全、语言、会话），无法关闭。',
    'analytics_title' => '分析',
    'analytics_desc' => '帮助我们了解网站的使用情况并改进体验。可选。',
    'always_on' => '始终启用',

    'save' => '保存',
    'cancel' => '取消',

    'link_privacy' => '隐私',
    'link_cookies' => 'Cookie 政策',
  ],

  'legal' => [
    'privacy_pill' => '隐私',
    'privacy_title' => '隐私政策',
    'privacy_lead' => '清晰说明我们如何收集与使用个人数据（联系与预订）。',

    'cookies_pill' => 'Cookie',
    'cookies_title' => 'Cookie 政策',
    'cookies_lead' => '我们使用哪些 Cookie 以及如何管理偏好设置。',

    'cta_contact' => '联系我们',
    'cta_cookies' => 'Cookie 政策',
    'cta_privacy' => '隐私',
  ],
  // Price match promise (Oct 2026)
  'pricematch' => [
    'title' => '最优价格保证',
    'text' => '在 Booking 上找到同样日期更便宜的价格？请通过 WhatsApp 或电子邮件发送截图给我们，我们将在该价格基础上再优惠 10%。',
    'cta_whatsapp' => '通过 WhatsApp 发送',
    'cta_email' => '通过电子邮件发送',
    'whatsapp_text' => '您好！我在 Booking 上找到了 Via Arriano 11 更低的价格。现发送截图以获得 10% 优惠。',
    'email_subject' => '在 Booking 上找到更低价格',
  ],

  // Neighbourhood guide (Oct 2026)
  'neighborhood_guide' => [
    'title' => 'Pigneto 区完全指南',
    'intro' => 'Pigneto 是罗马最地道、最有活力的街区之一：就在马焦雷门（Porta Maggiore）外，位于 Prenestina 大街和 Casilina 大街之间，乘地铁几分钟即可到达斗兽场。以下是住在这里的我们为您整理的到达前须知。',
    'sections' => [
      ['h' => 'Pigneto 街：早上逛市场，晚上喝开胃酒', 'html' => '街区的中心是步行街 <strong>Via del Pigneto</strong>。每天早上（周一至周六）这里有本地市场，出售水果、蔬菜和日常用品；晚上酒吧、葡萄酒吧和小餐馆把桌子摆满街道。这里是罗马本地人而不是旅行团出没的地方。'],
      ['h' => '电影与历史：帕索里尼的 Pigneto', 'html' => 'Pigneto 与意大利电影渊源深厚。皮埃尔·保罗·帕索里尼在这里拍摄了<em>《乞丐》（Accattone，1961）</em>，1924 年开业的老店 <strong>Bar Necci</strong> 就与这部电影有关。罗西里尼<em>《罗马，不设防的城市》</em>的部分场景也在附近的 Montecuccoli 街拍摄。漫步其中，仍能看到老工人街区低矮的房屋和庭院。'],
      ['h' => '街头艺术与街区生活', 'html' => 'Pigneto 和相邻的 Torpignattara 区墙上布满壁画和街头艺术，在小巷里散步就是参观一座免费的露天画廊。独立书店、古着店和手工作坊共同构成了这个街区独特的气质。'],
      ['h' => '周边景点', 'html' => '几分钟路程即可到达<strong>马焦雷门</strong>，那里有面包师欧律萨克斯的古墓（公元前 1 世纪），以及<strong>耶路撒冷圣十字圣殿</strong>。乘地铁几分钟可到<strong>拉特朗圣若望大殿</strong>，大学区 <strong>San Lorenzo</strong> 也近在咫尺。'],
      ['h' => '交通：地铁 C 线', 'html' => '地铁 C 线车站距公寓<strong>步行 3–5 分钟</strong>。C 线<strong>无需换乘即可到达 San Giovanni</strong>（可在此换乘 A 线前往西班牙广场和梵蒂冈）以及新开通的 <strong>Colosseo/Fori Imperiali 站</strong>：从门口到斗兽场约 15 分钟。沿 Prenestina 大街行驶的 <strong>14 路有轨电车</strong>可直达特米尼火车站。'],
      ['h' => '从机场和特米尼车站如何到达', 'html' => '<strong>从菲乌米奇诺机场：</strong>乘 Leonardo Express 列车到特米尼车站（约 30 分钟），再换乘 14 路有轨电车或短程出租车。直接打车约 45–60 分钟，按计价器收费（机场固定价格仅适用于奥勒良城墙以内）。<br><strong>从钱皮诺机场：</strong>乘机场大巴到特米尼车站，再换乘 14 路有轨电车，或打车（约 30 分钟）。<br><strong>从特米尼车站：</strong>乘 14 路有轨电车（往 Togliatti 方向），或乘地铁 A 线到 San Giovanni 再换乘 C 线。'],
    ],
  ],

  // Redesign v2 (Oct 2026)
  'v2' => [
    'title' => '从家门口到斗兽场，无需换乘',
    'start' => '您在罗马的家',
    'walk' => '步行 3–5 分钟',
    'metro_a' => '可换乘地铁 A 线',
    'colosseo' => '门到门约 15 分钟',
    'place' => '罗马 Pigneto',
    'skip' => '跳到正文',
    'photos' => '公寓照片',
  ],
];
