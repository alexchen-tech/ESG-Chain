import { Injectable, NotFoundException } from '@nestjs/common'
import { PrismaService } from '../../prisma/prisma.service'

@Injectable()
export class TradeGoodsService {
  constructor(private prisma: PrismaService) {}

  async list(query: {
    search?: string
    cbamApplicable?: boolean | string
    cbamSector?: string
    supplierId?: string
    page?: number
    limit?: number
  }) {
    const { search, cbamApplicable, cbamSector, supplierId, page = 1, limit = 15 } = query
    const skip = (page - 1) * limit

    const where: any = {}
    if (search) {
      where.OR = [
        { name: { contains: search, mode: 'insensitive' } },
        { hsCode: { contains: search, mode: 'insensitive' } },
        { nameZh: { contains: search, mode: 'insensitive' } },
      ]
    }
    if (cbamApplicable !== undefined && cbamApplicable !== '') {
      where.cbamApplicable = cbamApplicable === true || cbamApplicable === 'true'
    }
    if (cbamSector) where.cbamSector = cbamSector
    if (supplierId) where.supplierId = supplierId

    const [data, total] = await Promise.all([
      this.prisma.tradeGood.findMany({
        where,
        skip,
        take: limit,
        orderBy: { createdAt: 'desc' },
        include: { supplier: { select: { name: true } } },
      }),
      this.prisma.tradeGood.count({ where }),
    ])

    return { data, total, page, limit }
  }

  async findOne(id: string) {
    const good = await this.prisma.tradeGood.findUnique({
      where: { id },
      include: { supplier: true, pcfRecords: true },
    })
    if (!good) throw new NotFoundException('貿易商品不存在')
    return good
  }

  async create(dto: any) {
    return this.prisma.tradeGood.create({ data: dto })
  }

  async update(id: string, dto: any) {
    const good = await this.prisma.tradeGood.findUnique({ where: { id } })
    if (!good) throw new NotFoundException('貿易商品不存在')
    return this.prisma.tradeGood.update({ where: { id }, data: dto })
  }
}
