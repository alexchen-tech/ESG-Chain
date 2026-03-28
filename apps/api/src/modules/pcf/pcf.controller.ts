import { Controller, Get, Post, Patch, Body, Param, Query, UseGuards } from '@nestjs/common'
import { PCFService } from './pcf.service'
import { JwtAuthGuard } from '../../common/guards/jwt-auth.guard'
import { ApiTags, ApiBearerAuth } from '@nestjs/swagger'

@ApiTags('pcf')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard)
@Controller('pcf')
export class PCFController {
  constructor(private pcfService: PCFService) {}

  @Get()
  list(@Query() query: any) {
    return this.pcfService.list({
      ...query,
      page: query.page ? Number(query.page) : 1,
      limit: query.limit ? Number(query.limit) : 15,
    })
  }

  @Get(':id')
  findOne(@Param('id') id: string) {
    return this.pcfService.findOne(id)
  }

  @Post()
  create(@Body() body: any) {
    return this.pcfService.create(body)
  }

  @Patch(':id')
  update(@Param('id') id: string, @Body() body: any) {
    return this.pcfService.update(id, body)
  }
}
