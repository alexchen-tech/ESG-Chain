import { Controller, Get, Post, Patch, Body, Param, Query, UseGuards } from '@nestjs/common'
import { TradeGoodsService } from './trade-goods.service'
import { JwtAuthGuard } from '../../common/guards/jwt-auth.guard'
import { ApiTags, ApiBearerAuth } from '@nestjs/swagger'

@ApiTags('trade-goods')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard)
@Controller('trade-goods')
export class TradeGoodsController {
  constructor(private tradeGoodsService: TradeGoodsService) {}

  @Get()
  list(@Query() query: any) {
    return this.tradeGoodsService.list({
      ...query,
      page: query.page ? Number(query.page) : 1,
      limit: query.limit ? Number(query.limit) : 15,
    })
  }

  @Get(':id')
  findOne(@Param('id') id: string) {
    return this.tradeGoodsService.findOne(id)
  }

  @Post()
  create(@Body() body: any) {
    return this.tradeGoodsService.create(body)
  }

  @Patch(':id')
  update(@Param('id') id: string, @Body() body: any) {
    return this.tradeGoodsService.update(id, body)
  }
}
