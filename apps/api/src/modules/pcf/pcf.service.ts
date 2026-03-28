import { Injectable, NotFoundException } from '@nestjs/common'
import { PrismaService } from '../../prisma/prisma.service'

@Injectable()
export class PCFService {
  constructor(private prisma: PrismaService) {}

  async list(query: {
    search?: string
    status?: string
    supplierId?: string
    year?: number
    page?: number
    limit?: number
  }) {
    const { search, status, supplierId, year, page = 1, limit = 15 } = query
    const skip = (page - 1) * limit

    const where: any = {}
    if (search) {
      where.OR = [
        { productName: { contains: search, mode: 'insensitive' } },
        { supplier: { name: { contains: search, mode: 'insensitive' } } },
      ]
    }
    if (status) where.status = status
    if (supplierId) where.supplierId = supplierId
    if (year) where.year = Number(year)

    const [data, total] = await Promise.all([
      this.prisma.pCFRecord.findMany({
        where,
        skip,
        take: limit,
        orderBy: { createdAt: 'desc' },
        include: { supplier: { select: { name: true } } },
      }),
      this.prisma.pCFRecord.count({ where }),
    ])

    return { data, total, page, limit }
  }

  async findOne(id: string) {
    const record = await this.prisma.pCFRecord.findUnique({
      where: { id },
      include: { supplier: true, tradeGood: true },
    })
    if (!record) throw new NotFoundException('碳足跡記錄不存在')
    return record
  }

  async create(dto: any) {
    // Auto-calculate totalKgCO2e
    const scope1 = Number(dto.scope1KgCO2e ?? 0)
    const scope2 = Number(dto.scope2KgCO2e ?? 0)
    const scope3 = Number(dto.scope3KgCO2e ?? 0)
    const totalKgCO2e = scope1 + scope2 + scope3

    return this.prisma.pCFRecord.create({
      data: { ...dto, scope1KgCO2e: scope1, scope2KgCO2e: scope2, scope3KgCO2e: scope3, totalKgCO2e },
    })
  }

  async update(id: string, dto: any) {
    const record = await this.prisma.pCFRecord.findUnique({ where: { id } })
    if (!record) throw new NotFoundException('碳足跡記錄不存在')

    const data: any = { ...dto }
    // Recalculate total if scopes provided
    if (dto.scope1KgCO2e !== undefined || dto.scope2KgCO2e !== undefined || dto.scope3KgCO2e !== undefined) {
      const scope1 = Number(dto.scope1KgCO2e ?? record.scope1KgCO2e)
      const scope2 = Number(dto.scope2KgCO2e ?? record.scope2KgCO2e)
      const scope3 = Number(dto.scope3KgCO2e ?? record.scope3KgCO2e)
      data.totalKgCO2e = scope1 + scope2 + scope3
    }

    return this.prisma.pCFRecord.update({ where: { id }, data })
  }
}
