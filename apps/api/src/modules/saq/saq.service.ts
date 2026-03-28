import { Injectable, NotFoundException, BadRequestException } from '@nestjs/common'
import { PrismaService } from '../../prisma/prisma.service'

@Injectable()
export class SAQService {
  constructor(private prisma: PrismaService) {}

  async list(query: {
    search?: string
    status?: string
    supplierId?: string
    page?: number
    limit?: number
  }) {
    const { search, status, supplierId, page = 1, limit = 15 } = query
    const skip = (page - 1) * limit

    const where: any = {}
    if (search) {
      where.OR = [
        { period: { contains: search, mode: 'insensitive' } },
        { supplier: { name: { contains: search, mode: 'insensitive' } } },
      ]
    }
    if (status) where.status = status
    if (supplierId) where.supplierId = supplierId

    const [data, total] = await Promise.all([
      this.prisma.sAQ.findMany({
        where,
        skip,
        take: limit,
        orderBy: { createdAt: 'desc' },
        include: {
          supplier: { select: { name: true } },
          template: { select: { name: true, nameZh: true } },
        },
      }),
      this.prisma.sAQ.count({ where }),
    ])

    return { data, total, page, limit }
  }

  async findOne(id: string) {
    const saq = await this.prisma.sAQ.findUnique({
      where: { id },
      include: {
        supplier: true,
        template: { include: { questions: { orderBy: { order: 'asc' } } } },
        responses: true,
        createdBy: { select: { name: true, email: true } },
      },
    })
    if (!saq) throw new NotFoundException('問卷不存在')
    return saq
  }

  async create(dto: any, userId: string) {
    return this.prisma.sAQ.create({
      data: {
        ...dto,
        createdById: userId,
        dueDate: new Date(dto.dueDate),
      },
    })
  }

  async update(id: string, dto: any) {
    const saq = await this.prisma.sAQ.findUnique({ where: { id } })
    if (!saq) throw new NotFoundException('問卷不存在')
    return this.prisma.sAQ.update({ where: { id }, data: dto })
  }

  async send(id: string) {
    const saq = await this.prisma.sAQ.findUnique({ where: { id } })
    if (!saq) throw new NotFoundException('問卷不存在')
    if (saq.status !== 'draft') throw new BadRequestException('只有草稿狀態的問卷可以發送')

    return this.prisma.sAQ.update({
      where: { id },
      data: { status: 'sent', sentAt: new Date() },
    })
  }

  async submit(id: string, responses: any[]) {
    const saq = await this.prisma.sAQ.findUnique({ where: { id } })
    if (!saq) throw new NotFoundException('問卷不存在')

    // Create responses
    if (responses && responses.length > 0) {
      await this.prisma.sAQResponse.createMany({
        data: responses.map(r => ({
          saqId: id,
          questionId: r.questionId,
          answer: String(r.answer),
          evidence: r.evidence,
          comment: r.comment,
        })),
        skipDuplicates: true,
      })
    }

    return this.prisma.sAQ.update({
      where: { id },
      data: { status: 'submitted', submittedAt: new Date() },
    })
  }
}
