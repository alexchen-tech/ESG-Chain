import { PrismaClient } from '@prisma/client'
import * as bcrypt from 'bcrypt'

const prisma = new PrismaClient()

async function main() {
  console.log('開始植入種子資料...')

  // ── 建立使用者 ───────────────────────────────────────────────────────────────
  const password = await bcrypt.hash('demo1234', 12)

  const admin = await prisma.user.upsert({
    where: { email: 'admin@esgchain.com' },
    update: {},
    create: {
      email: 'admin@esgchain.com',
      name: '系統管理員',
      password,
      role: 'admin',
      status: 'active',
    },
  })

  const buyer = await prisma.user.upsert({
    where: { email: 'buyer@esgchain.com' },
    update: {},
    create: {
      email: 'buyer@esgchain.com',
      name: '採購專員 王小明',
      password,
      role: 'buyer',
      status: 'active',
    },
  })

  const sustain = await prisma.user.upsert({
    where: { email: 'sustain@esgchain.com' },
    update: {},
    create: {
      email: 'sustain@esgchain.com',
      name: '永續長 李美華',
      password,
      role: 'sustain',
      status: 'active',
    },
  })

  const analyst = await prisma.user.upsert({
    where: { email: 'analyst@esgchain.com' },
    update: {},
    create: {
      email: 'analyst@esgchain.com',
      name: '分析師 陳志偉',
      password,
      role: 'analyst',
      status: 'active',
    },
  })

  console.log('✓ 使用者建立完成')

  // ── 建立供應商 ───────────────────────────────────────────────────────────────
  const suppliers = await Promise.all([
    prisma.supplier.upsert({
      where: { code: 'SC20240001' },
      update: {},
      create: {
        code: 'SC20240001',
        name: '台灣精密鋼鐵股份有限公司',
        nameEn: 'Taiwan Precision Steel Co., Ltd.',
        tier: 'tier1',
        country: 'TW',
        industry: '鋼鐵製造',
        status: 'active',
        riskLevel: 'medium',
        riskScore: 45.2,
        carbonIntensity: 1.82,
        contactEmail: 'contact@tpsteel.com.tw',
        certifications: ['ISO 14001', 'ISO 50001'],
        employeeCount: 2500,
        annualRevenue: 8500000000,
      },
    }),
    prisma.supplier.upsert({
      where: { code: 'SC20240002' },
      update: {},
      create: {
        code: 'SC20240002',
        name: '越南綠能電子有限公司',
        nameEn: 'Vietnam Green Electronics Ltd.',
        tier: 'tier1',
        country: 'VN',
        industry: '電子零組件',
        status: 'active',
        riskLevel: 'high',
        riskScore: 72.8,
        carbonIntensity: 2.45,
        contactEmail: 'esg@vge.vn',
        certifications: ['RBA'],
        employeeCount: 8000,
      },
    }),
    prisma.supplier.upsert({
      where: { code: 'SC20240003' },
      update: {},
      create: {
        code: 'SC20240003',
        name: '馬來西亞化學工業集團',
        nameEn: 'Malaysia Chemical Industries Group',
        tier: 'tier2',
        country: 'MY',
        industry: '化工製造',
        status: 'active',
        riskLevel: 'critical',
        riskScore: 88.5,
        carbonIntensity: 4.12,
        contactEmail: 'compliance@mcig.my',
        certifications: [],
        employeeCount: 3200,
      },
    }),
    prisma.supplier.upsert({
      where: { code: 'SC20240004' },
      update: {},
      create: {
        code: 'SC20240004',
        name: '泰國農業加工企業',
        nameEn: 'Thailand Agro Processing Enterprise',
        tier: 'tier2',
        country: 'TH',
        industry: '農業加工',
        status: 'pending',
        riskLevel: 'low',
        riskScore: 22.1,
        carbonIntensity: 0.95,
        contactEmail: 'info@tape.co.th',
        certifications: ['GlobalG.A.P.'],
        employeeCount: 650,
      },
    }),
    prisma.supplier.upsert({
      where: { code: 'SC20240005' },
      update: {},
      create: {
        code: 'SC20240005',
        name: '中國寧波包裝材料廠',
        nameEn: 'Ningbo Packaging Materials Factory',
        tier: 'tier3',
        country: 'CN',
        industry: '包裝材料',
        status: 'active',
        riskLevel: 'medium',
        riskScore: 51.3,
        carbonIntensity: 1.35,
        contactEmail: 'sales@nbpackaging.cn',
        certifications: ['FSC'],
        employeeCount: 420,
      },
    }),
  ])

  console.log('✓ 供應商建立完成')

  // ── 建立 SAQ 範本 ────────────────────────────────────────────────────────────
  const template = await prisma.sAQTemplate.upsert({
    where: { id: 'tpl-esg-2024' },
    update: {},
    create: {
      id: 'tpl-esg-2024',
      name: 'ESG Supplier Assessment 2024',
      nameZh: '2024年供應商ESG評估問卷',
      category: 'comprehensive',
      isActive: true,
      questions: {
        create: [
          {
            section: '環境管理',
            question: 'Does your company have an environmental management system (EMS)?',
            questionZh: '貴公司是否建立環境管理系統（EMS）？',
            type: 'yes_no',
            required: true,
            weight: 2.0,
            order: 1,
          },
          {
            section: '環境管理',
            question: 'What is your annual GHG emissions (Scope 1+2) in tCO2e?',
            questionZh: '貴公司年度溫室氣體排放量（範疇一+二）為多少公噸CO2e？',
            type: 'number',
            required: true,
            weight: 3.0,
            order: 2,
          },
          {
            section: '環境管理',
            question: 'Do you have a carbon reduction target?',
            questionZh: '貴公司是否設有碳減量目標？',
            type: 'yes_no',
            required: true,
            weight: 2.5,
            order: 3,
          },
          {
            section: '環境管理',
            question: 'Describe your waste management practices.',
            questionZh: '請描述貴公司的廢棄物管理措施。',
            type: 'text',
            required: false,
            weight: 1.5,
            order: 4,
          },
          {
            section: '社會責任',
            question: 'Does your company comply with local labor laws including minimum wage and working hours?',
            questionZh: '貴公司是否遵守當地勞動法規，包括最低工資及工時規定？',
            type: 'yes_no',
            required: true,
            weight: 3.0,
            order: 5,
          },
          {
            section: '社會責任',
            question: 'Do you have a policy prohibiting child labor and forced labor?',
            questionZh: '貴公司是否有禁止童工和強迫勞動的政策？',
            type: 'yes_no',
            required: true,
            weight: 3.0,
            order: 6,
          },
          {
            section: '社會責任',
            question: 'What is your employee injury rate (per 1000 employees)?',
            questionZh: '貴公司員工職災率為何（每千名員工）？',
            type: 'number',
            required: false,
            weight: 2.0,
            order: 7,
          },
          {
            section: '公司治理',
            question: 'Does your company have a code of business conduct and ethics?',
            questionZh: '貴公司是否有商業行為準則和道德規範？',
            type: 'yes_no',
            required: true,
            weight: 2.0,
            order: 8,
          },
          {
            section: '公司治理',
            question: 'Do you have an anti-corruption and anti-bribery policy?',
            questionZh: '貴公司是否有反腐敗和反賄賂政策？',
            type: 'yes_no',
            required: true,
            weight: 2.5,
            order: 9,
          },
          {
            section: '公司治理',
            question: 'Do you conduct regular ESG/sustainability reporting?',
            questionZh: '貴公司是否定期發布ESG/永續報告？',
            type: 'yes_no',
            required: false,
            weight: 1.5,
            order: 10,
          },
        ],
      },
    },
  })

  console.log('✓ SAQ 範本建立完成')

  // ── 建立 SAQ ─────────────────────────────────────────────────────────────────
  const saq1 = await prisma.sAQ.upsert({
    where: { id: 'saq-001' },
    update: {},
    create: {
      id: 'saq-001',
      supplierId: suppliers[0].id,
      templateId: template.id,
      period: '2024-FY',
      status: 'submitted',
      score: 72.5,
      sentAt: new Date('2024-10-01'),
      submittedAt: new Date('2024-10-28'),
      dueDate: new Date('2024-11-30'),
      createdById: sustain.id,
    },
  })

  const saq2 = await prisma.sAQ.upsert({
    where: { id: 'saq-002' },
    update: {},
    create: {
      id: 'saq-002',
      supplierId: suppliers[1].id,
      templateId: template.id,
      period: '2024-FY',
      status: 'in_progress',
      sentAt: new Date('2024-10-15'),
      dueDate: new Date('2024-12-15'),
      createdById: sustain.id,
    },
  })

  console.log('✓ SAQ 建立完成')

  // ── 建立 CAP ─────────────────────────────────────────────────────────────────
  await prisma.cAP.upsert({
    where: { id: 'cap-001' },
    update: {},
    create: {
      id: 'cap-001',
      supplierId: suppliers[2].id,
      title: '化學廢水排放超標矯正行動',
      status: 'open',
      riskLevel: 'critical',
      dueDate: new Date('2025-03-31'),
      assigneeId: sustain.id,
      findings: {
        create: [
          {
            category: '環境合規',
            description: '廠區廢水COD值超過法定排放標準3倍以上，存在嚴重環境污染風險',
            recommendation: '立即停止排放，安裝廢水處理設備，取得合格排放證明後方可恢復生產',
            evidence: null,
          },
        ],
      },
    },
  })

  await prisma.cAP.upsert({
    where: { id: 'cap-002' },
    update: {},
    create: {
      id: 'cap-002',
      supplierId: suppliers[1].id,
      saqId: saq2.id,
      title: '勞工工時超時問題改善',
      status: 'in_progress',
      riskLevel: 'high',
      dueDate: new Date('2025-06-30'),
      assigneeId: buyer.id,
      findings: {
        create: [
          {
            category: '勞工權益',
            description: '員工月加班時數超過60小時，違反RBA行為準則要求',
            recommendation: '重新規劃生產排班，確保每月加班時數不超過60小時，並建立加班審批機制',
          },
        ],
      },
    },
  })

  console.log('✓ CAP 建立完成')

  // ── 建立貿易商品 ──────────────────────────────────────────────────────────────
  await prisma.tradeGood.upsert({
    where: { id: 'tg-001' },
    update: {},
    create: {
      id: 'tg-001',
      supplierId: suppliers[0].id,
      name: 'Hot-rolled Steel Coil',
      nameZh: '熱軋鋼捲',
      hsCode: '7208.51',
      cbamApplicable: true,
      cbamSector: 'steel',
      annualVolume: 15000,
      unit: '公噸',
      countryOfOrigin: 'TW',
    },
  })

  await prisma.tradeGood.upsert({
    where: { id: 'tg-002' },
    update: {},
    create: {
      id: 'tg-002',
      supplierId: suppliers[2].id,
      name: 'Urea Fertilizer',
      nameZh: '尿素化肥',
      hsCode: '3102.10',
      cbamApplicable: true,
      cbamSector: 'fertiliser',
      annualVolume: 5000,
      unit: '公噸',
      countryOfOrigin: 'MY',
    },
  })

  await prisma.tradeGood.upsert({
    where: { id: 'tg-003' },
    update: {},
    create: {
      id: 'tg-003',
      supplierId: suppliers[4].id,
      name: 'Corrugated Cardboard Box',
      nameZh: '瓦楞紙箱',
      hsCode: '4819.10',
      cbamApplicable: false,
      annualVolume: 500000,
      unit: '個',
      countryOfOrigin: 'CN',
    },
  })

  console.log('✓ 貿易商品建立完成')

  // ── 建立 PCF 記錄 ─────────────────────────────────────────────────────────────
  await prisma.pCFRecord.upsert({
    where: { id: 'pcf-001' },
    update: {},
    create: {
      id: 'pcf-001',
      supplierId: suppliers[0].id,
      tradeGoodId: 'tg-001',
      productName: '熱軋鋼捲 (HR Steel Coil)',
      functionalUnit: '每公噸產品',
      scope1KgCO2e: 850.5,
      scope2KgCO2e: 320.2,
      scope3KgCO2e: 180.8,
      totalKgCO2e: 1351.5,
      year: 2024,
      status: 'verified',
      methodology: 'GHG Protocol',
      verifiedAt: new Date('2024-09-15'),
    },
  })

  await prisma.pCFRecord.upsert({
    where: { id: 'pcf-002' },
    update: {},
    create: {
      id: 'pcf-002',
      supplierId: suppliers[1].id,
      productName: '電子連接器模組',
      functionalUnit: '每千個連接器',
      scope1KgCO2e: 12.4,
      scope2KgCO2e: 85.6,
      scope3KgCO2e: 42.1,
      totalKgCO2e: 140.1,
      year: 2024,
      status: 'submitted',
      methodology: 'ISO 14067',
    },
  })

  console.log('✓ PCF 記錄建立完成')

  // ── 建立減碳計畫 ──────────────────────────────────────────────────────────────
  await prisma.decarbPlan.upsert({
    where: { id: 'dp-001' },
    update: {},
    create: {
      id: 'dp-001',
      supplierId: suppliers[0].id,
      baselineYear: 2020,
      targetYear: 2030,
      baselineEmissions: 125000,
      targetReduction: 42,
      sbtiAligned: true,
      status: 'on_track',
      milestones: {
        create: [
          {
            year: 2025,
            targetEmissions: 100000,
            actualEmissions: 98500,
            initiative: '導入電弧爐煉鋼技術，降低直接排放',
            status: 'achieved',
          },
          {
            year: 2027,
            targetEmissions: 85000,
            initiative: '採購再生能源電力，提高綠電比例至60%',
            status: 'in_progress',
          },
          {
            year: 2030,
            targetEmissions: 72500,
            initiative: '完成碳捕捉系統建置，剩餘排放透過自願碳市場中和',
            status: 'planned',
          },
        ],
      },
    },
  })

  console.log('✓ 減碳計畫建立完成')

  // ── 建立通知 ─────────────────────────────────────────────────────────────────
  await prisma.notification.createMany({
    data: [
      {
        userId: sustain.id,
        type: 'saq_due',
        title: '問卷即將到期提醒',
        body: '越南綠能電子 2024-FY 問卷將於 12/15 到期，請督促供應商盡快提交',
        isRead: false,
        linkTo: '/saq',
      },
      {
        userId: sustain.id,
        type: 'risk_alert',
        title: '高風險供應商警示',
        body: '馬來西亞化學工業集團風險評分已達 88.5，建議立即展開實地稽核',
        isRead: false,
        linkTo: '/suppliers',
      },
      {
        userId: admin.id,
        type: 'cap_overdue',
        title: '矯正行動逾期警告',
        body: '越南綠能電子勞工工時超時問題 CAP 已接近截止日期',
        isRead: true,
        linkTo: '/cap',
      },
    ],
    skipDuplicates: true,
  })

  console.log('✓ 通知建立完成')

  console.log('\n種子資料植入完成！')
  console.log('測試帳號：')
  console.log('  Admin:   admin@esgchain.com / demo1234')
  console.log('  Buyer:   buyer@esgchain.com / demo1234')
  console.log('  Sustain: sustain@esgchain.com / demo1234')
  console.log('  Analyst: analyst@esgchain.com / demo1234')
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect())
