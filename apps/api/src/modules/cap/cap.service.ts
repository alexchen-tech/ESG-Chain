import { Injectable, NotFoundException } from '@nestjs/common'
import { PrismaService } from '../../prisma/prisma.service'

@Injectable()
export class CAPService {
  constructor(private prisma: PrismaService) {}

  async list(query: {
    search?: string
    status?: string
    riskLevel?: string
    supplierId?: string
    page?: number
    limit?: number
  }) {
    const { search, status, riskLevel, supplierId, page = 1, limit = 15 } = query
    const skip = (page - 1) * limit

    const where: any = {}
    if (search) {
      where.OR = [
        { title: { contains: search, mode: 'insensitive' } },
        { supplier: { name: { contains: search, mode: 'insensitive' } } },
      ]
    }
    if (status) where.status = status
    if (riskLevel) where.riskLevel = riskLevel
    if (supplierId) where.supplierId = supplierId

    // Auto-update overdue
    await this.prisma.cAP.updateMany({
      where: {
        status: { in: ['open', 'in_progress'] },
        dueDate: { lt: new Date() },
      },
      data: { status: 'overdue' },
    })

    const [data, total] = await Promise.all([
      this.prisma.cAP.findMany({
        where,
        skip,
        take: limit,
        orderBy: { createdAt: 'desc' },
        include: {
          supplier: { select: { name: true } },
          findings: true,
        },
      }),
      this.prisma.cAP.count({ where }),
    ])

    return { data, total, page, limit }
  }

  async findOne(id: string) {
    const cap = await this.prisma.cAP.findUnique({
      where: { id },
      include: {
        supplier: true,
        saq: { select: { period: true } },
        findings: true,
      },
    })
    if (!cap) throw new NotFoundException('矯正行動不存在')
    return cap
  }

  async create(dto: any) {
    return this.prisma.cAP.create({
      data: {
        ...dto,
        dueDate: new Date(dto.dueDate),
      },
    })
  }

  async update(id: string, dto: any) {
    const cap = await this.prisma.cAP.findUnique({ where: { id } })
    if (!cap) throw new NotFoundException('矯正行動不存在')
    const data: any = { ...dto }
    if (dto.dueDate) data.dueDate = new Date(dto.dueDate)
    return this.prisma.cAP.update({ where: { id }, data })
  }

  async close(id: string) {
    const cap = await this.prisma.cAP.findUnique({ where: { id } })
    if (!cap) throw new NotFoundException('矯正行動不存在')
    return this.prisma.cAP.update({
      where: { id },
      data: { status: 'closed', closedAt: new Date() },
    })
  }
}
