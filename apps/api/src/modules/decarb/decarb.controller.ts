import { Controller, Get, Post, Patch, Body, Param, Query, UseGuards } from '@nestjs/common'
import { DecarbService } from './decarb.service'
import { JwtAuthGuard } from '../../common/guards/jwt-auth.guard'
import { ApiTags, ApiBearerAuth } from '@nestjs/swagger'

@ApiTags('decarb')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard)
@Controller('decarb')
export class DecarbController {
  constructor(private decarbService: DecarbService) {}

  @Get()
  list(@Query() query: any) {
    return this.decarbService.list({
      ...query,
      page: query.page ? Number(query.page) : 1,
      limit: query.limit ? Number(query.limit) : 15,
    })
  }

  @Get(':id')
  findOne(@Param('id') id: string) {
    return this.decarbService.findOne(id)
  }

  @Post()
  create(@Body() body: any) {
    return this.decarbService.create(body)
  }

  @Patch(':id')
  update(@Param('id') id: string, @Body() body: any) {
    return this.decarbService.update(id, body)
  }
}
