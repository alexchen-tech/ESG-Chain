import { Controller, Get, Post, Patch, Body, Param, Query, UseGuards } from '@nestjs/common'
import { ReportsService } from './reports.service'
import { JwtAuthGuard } from '../../common/guards/jwt-auth.guard'
import { CurrentUser } from '../../common/decorators/current-user.decorator'
import { ApiTags, ApiBearerAuth } from '@nestjs/swagger'

@ApiTags('reports')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard)
@Controller('reports')
export class ReportsController {
  constructor(private reportsService: ReportsService) {}

  @Get()
  list(@Query() query: any) {
    return this.reportsService.list({
      ...query,
      page: query.page ? Number(query.page) : 1,
      limit: query.limit ? Number(query.limit) : 15,
    })
  }

  @Get(':id')
  findOne(@Param('id') id: string) {
    return this.reportsService.findOne(id)
  }

  @Post()
  create(@Body() body: any, @CurrentUser() user: any) {
    return this.reportsService.create(body, user.id)
  }

  @Patch(':id')
  update(@Param('id') id: string, @Body() body: any) {
    return this.reportsService.update(id, body)
  }
}
