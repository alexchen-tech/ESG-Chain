import { Controller, Get, Post, Patch, Body, Param, Query, UseGuards } from '@nestjs/common'
import { SAQService } from './saq.service'
import { JwtAuthGuard } from '../../common/guards/jwt-auth.guard'
import { CurrentUser } from '../../common/decorators/current-user.decorator'
import { ApiTags, ApiBearerAuth } from '@nestjs/swagger'

@ApiTags('saq')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard)
@Controller('saq')
export class SAQController {
  constructor(private saqService: SAQService) {}

  @Get()
  list(@Query() query: any) {
    return this.saqService.list({
      ...query,
      page: query.page ? Number(query.page) : 1,
      limit: query.limit ? Number(query.limit) : 15,
    })
  }

  @Get(':id')
  findOne(@Param('id') id: string) {
    return this.saqService.findOne(id)
  }

  @Post()
  create(@Body() body: any, @CurrentUser() user: any) {
    return this.saqService.create(body, user.id)
  }

  @Patch(':id')
  update(@Param('id') id: string, @Body() body: any) {
    return this.saqService.update(id, body)
  }

  @Post(':id/send')
  send(@Param('id') id: string) {
    return this.saqService.send(id)
  }

  @Post(':id/submit')
  submit(@Param('id') id: string, @Body() body: { responses: any[] }) {
    return this.saqService.submit(id, body.responses)
  }
}
