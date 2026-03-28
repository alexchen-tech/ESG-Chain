import { Injectable, NotFoundException } from '@nestjs/common'
import { PrismaService } from '../../prisma/prisma.service'

@Injectable()
export class SuppliersService {
  constructor(private prisma: PrismaService) {}

  async list(query: {
    search?: string
    tier?: string
    status?: string
    riskLevel?: string
    page?: number
    limit?: number
  }) {
    const { search, tier, status, riskLevel, page = 1, limit = 15 } = query
    const skip = (page - 1) * limit

    const where: any = {}
    if (search) {
      where.OR = [
        { name: { contains: search, mode: 'insensitive' } },
        { code: { contains: search, mode: 'insensitive' } },
        { nameEn: { contains: search, mode: 'insensitive' } },
      ]
    }
    if (tier) where.tier = tier
    if (status) where.status = status
    if (riskLevel) where.riskLevel = riskLevel

    const [data, total] = await Promise.all([
      this.prisma.supplier.findMany({
        where,
        skip,
        take: limit,
        orderBy: { createdAt: 'desc' },
      }),
      this.prisma.supplier.count({ where }),
    ])

    return { data, total, page, limit }
  }

  async findOne(id: string) {
    const supplier = await this.prisma.supplier.findUnique({
      where: { id },
      include: {
        contacts: true,
        saqs: { include: { template: true }, orderBy: { createdAt: 'desc' }, take: 5 },
        caps: { orderBy: { createdAt: 'desc' }, take: 5 },
      },
    })
    if (!supplier) throw new NotFoundException('供應商不存在')
    return supplier
  }

  async create(dto: any) {
    // Auto-generate supplier code: SC + year + 4-digit seq
    const year = new Date().getFullYear()
    const prefix = `SC${year}`
    const lastSupplier = await this.prisma.supplier.findFirst({
      where: { code: { startsWith: prefix } },
      orderBy: { code: 'desc' },
    })
    let seq = 1
    if (lastSupplier) {
      const lastSeq = parseInt(lastSupplier.code.replace(prefix, ''), 10)
      if (!isNaN(lastSeq)) seq = lastSeq + 1
    }
    const code = `${prefix}${String(seq).padStart(4, '0')}`

    return this.prisma.supplier.create({
      data: { ...dto, code },
    })
  }

  async update(id: string, dto: any) {
    const supplier = await this.prisma.supplier.findUnique({ where: { id } })
    if (!supplier) throw new NotFoundException('供應商不存在')
    return this.prisma.supplier.update({ where: { id }, data: dto })
  }

  async remove(id: string) {
    const supplier = await this.prisma.supplier.findUnique({ where: { id } })
    if (!supplier) throw new NotFoundException('供應商不存在')
    return this.prisma.supplier.delete({ where: { id } })
  }
}
